<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Helper;
use App\Helpers\Njuskalo;
use App\Helpers\Xmlexport;
use App\Helpers\Recaptcha;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Mail\ContactFormMessage;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Marketing\Wishlist;
use App\Models\Front\Loyalty;
use App\Models\Front\Page;
use App\Models\Sitemap;
use App\Services\Front\CuratedCollectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\Facades\Image;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class HomeController extends Controller
{
    private const HOMEPAGE_DESCRIPTION_SNAPSHOT_FILE = 'app/homepage-description-snapshot.json';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, CuratedCollectionService $curatedCollectionService)
    {
        $page = Cache::remember('page.homepage', config('cache.life'), function () {
            return Page::where('slug', 'homepage')->first();
        });

        $homeSalesWidget = view('front.layouts.partials.home-sales-widget', $curatedCollectionService->homepageWidgetData())->render();
        $page->description = str_replace(
            '<!--home-sales-widget-->',
            $homeSalesWidget,
            $this->resolveHomepageDescriptionSnapshot($page)
        );

        return view('front.page', compact('page'));
    }



    /**
     * @param Page $page
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function page(Page $page)
    {
        return view('front.page', compact('page'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function wishlist(Request $request)
    {
        $wish = new Wishlist();
        $wish->validateRequest($request);

        // recaptcha verifikacija – moraš imati site & secret key postavljen
        $recaptcha = (new Recaptcha())->check($request->toArray());
        if (! $recaptcha || ! $recaptcha->ok()) {
            return back()->withErrors(['error' => 'ReCaptcha Error! Kontaktirajte administratora!'])
                ->withInput();
        }

        if ($wish->create()) {
            return back()->with(['success' => 'Vaš Email je upisan u listu želja za ovaj artikl..!']);
        }

        return back()->with(['error' => 'Wishlist Greška! Molimo vas kontaktirajte administratora!']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendProductComment(Request $request)
    {
        $recaptcha = (new Recaptcha())->check($request->toArray());

        if (! $recaptcha || ! $recaptcha->ok()) {
            return back()
                ->withInput()
                ->with([
                    'error'            => 'ReCaptcha provjera nije uspjela. Pokušajte ponovno.',
                    'review_submitted' => true,
                ]);
        }

        if (auth()->check() && Review::hasReachedMonthlyLimitForUser((int) auth()->id())) {
            $limit = Review::monthlyLimit();

            return back()
                ->withInput()
                ->with([
                    'error'            => 'Hvala vam na dosadašnjim komentarima. Ovaj mjesec ste već iskoristili maksimum od ' . $limit . ' komentara za loyalty nagradu.',
                    'review_submitted' => true,
                ]);
        }

        $review = new Review();

        $createdReview = $review->validateRequest($request)->create();

        if ($createdReview) {
            Loyalty::syncProductReviewReward($createdReview);

            $points = Review::rewardPoints();
            $limit = Review::monthlyLimit();

            return back()->with([
                'success'          => 'Hvala vam na komentaru. Nakon odobrenja bit će vidljiv na stranici, a registrirani kupci dobivaju ' . $points . ' loyalty bodova po odobrenom komentaru, do ' . $limit . ' komentara mjesečno.',
                'review_submitted' => true,
            ]);
        }

        return back()
            ->withInput()
            ->with([
                'error'            => 'Dogodila se greška prilikom spremanja komentara.',
                'review_submitted' => true,
            ]);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function contact(Request $request)
    {
        return view('front.contact');
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function sendContactMessage(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'message' => 'required',
        ]);

        // Recaptcha
        $recaptcha = (new Recaptcha())->check($request->toArray());

        if ( ! $recaptcha || ! $recaptcha->ok()) {
            return back()->withErrors(['error' => 'ReCaptcha Error! Kontaktirajte administratora!']);
        }

        dispatch(function () use ($request) {
            Mail::to(config('mail.admin'))->send(new ContactFormMessage($request->toArray()));
        })->afterResponse();

        return redirect()->route('kontakt')->with(['success' => 'Vaša poruka je uspješno poslana.! Odgovoriti ćemo vam uskoro.']);
    }


    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function imageCache(Request $request)
    {
        $src = $this->resolveReadableImageSource($request->input('src'));

        $cacheimage = Image::cache(function($image) use ($src) {
            $image->make($src);
        }, config('imagecache.lifetime'));

        return Image::make($cacheimage)->response();
    }


    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function thumbCache(Request $request)
    {
        $src = $this->resolveReadableImageSource($request->input('src'));
        [$width, $height] = $this->resolveThumbDimensions($request->input('size'));

        $cacheimage = Image::cache(function($image) use ($src, $width, $height) {
            $image->make($src)->resize($width, $height);

        }, config('imagecache.lifetime'));

        return Image::make($cacheimage)->response();
    }


    private function resolveReadableImageSource(?string $src): string
    {
        $fallback = public_path('media/img/knjiga-detalj.jpg');

        if (blank($src)) {
            return $fallback;
        }

        $path = $this->normalizeImagePath($src);

        if ($path && is_file($path)) {
            return $path;
        }

        return $fallback;
    }


    private function normalizeImagePath(string $src): ?string
    {
        if (filter_var($src, FILTER_VALIDATE_URL)) {
            $src = parse_url($src, PHP_URL_PATH) ?: '';
        }

        $clean = trim($src);

        if ($clean === '') {
            return null;
        }

        return public_path(ltrim($clean, '/'));
    }


    private function resolveThumbDimensions($size): array
    {
        $width = 250;
        $height = 300;

        if (blank($size)) {
            return [$width, $height];
        }

        $size = trim((string) $size);

        if (strpos($size, 'x') !== false) {
            [$requestedWidth, $requestedHeight] = array_pad(explode('x', $size, 2), 2, null);

            $width = max((int) $requestedWidth, 1);
            $height = max((int) $requestedHeight, 1);

            return [$width, $height];
        }

        return [max((int) $size, 1), $height];
    }


    /**
     * @param Request $request
     * @param null    $sitemap
     *
     * @return \Illuminate\Http\Response
     */
    public function sitemapXML(Request $request, $sitemap = null)
    {
        if ( ! $sitemap) {
            $items = config('settings.sitemap');

            return response()->view('front.layouts.partials.sitemap-index', [
                'items' => $items
            ])->header('Content-Type', 'text/xml');
        }

        if (in_array($sitemap, ['images', 'images.xml', 'img'], true)) {
            return $this->sitemapImageXML();
        }

        $sm = new Sitemap($sitemap);

        if (is_null($sm->getSitemap())) {
            abort(404);
        }

        return response()->view('front.layouts.partials.sitemap', [
            'items' => $sm->getSitemap()
        ])->header('Content-Type', 'text/xml');
    }


    /**
     * @return \Illuminate\Http\Response
     */
    public function sitemapImageXML()
    {
        $sm = new Sitemap('images');

        return response()->view('front.layouts.partials.sitemap-image', [
            'items' => $sm->getResponse()
        ])->header('Content-Type', 'text/xml');
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function njuskaloXML(Request $request)
    {
        $njuskalo = new Njuskalo();
        $path = $njuskalo->ensureExport();
        $ttl = (int) config('settings.njuskalo.http_cache_ttl', 3600);

        $response = response()->file($path, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);

        $response->setPublic();
        $response->setMaxAge($ttl);
        $response->setSharedMaxAge($ttl);
        $response->setAutoLastModified();
        $response->setAutoEtag();

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function xmlexport(Request $request)
    {
        $xmlexport = new Xmlexport();

        return response()->view('front.layouts.partials.xmlexport', [
            'items' => $xmlexport->getItems()
        ])->header('Content-Type', 'text/xml');
    }


    private function resolveHomepageDescriptionSnapshot(Page $page): string
    {
        $signature = sha1(implode('|', [
            (string) $page->id,
            (string) (optional($page->updated_at)->timestamp ?? 0),
            md5((string) ($page->description ?? '')),
            md5((string) ($page->short_description ?? '')),
        ]));
        $path = storage_path(self::HOMEPAGE_DESCRIPTION_SNAPSHOT_FILE);

        if (File::exists($path)) {
            $snapshot = json_decode((string) File::get($path), true);

            if (is_array($snapshot) && data_get($snapshot, 'signature') === $signature && is_string(data_get($snapshot, 'html'))) {
                return (string) $snapshot['html'];
            }
        }

        $description = (string) ($page->description ?? '');

        if (str_contains($description, '++slider-index++')) {
            $description = preg_replace('/\+\+slider-index\+\+/', '++slider-index++<!--home-sales-widget-->', $description, 1) ?: $description;
        } else {
            $description = '<!--home-sales-widget-->' . $description;
        }

        $html = \App\Helpers\Helper::setDescription(
            $description,
            ['short_description' => $page->short_description ?? '']
        );

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'signature' => $signature,
            'html' => $html,
            'updated_at' => now()->toAtomString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $html;
    }

}
