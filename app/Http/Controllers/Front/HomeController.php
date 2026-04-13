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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\Facades\Image;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $page = Cache::remember('page.homepage', config('cache.life'), function () {
            return Page::where('slug', 'homepage')->first();
        });

        // ✅ Proslijedi short_description kao kontekst u Helper
        $page->description = \App\Helpers\Helper::setDescription(
            $page->description ?? '',
            ['short_description' => $page->short_description ?? '']
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

        $review = new Review();

        $createdReview = $review->validateRequest($request)->create();

        if ($createdReview) {
            $points = (int) config('settings.loyalty.product_review', 0);

            if ($points > 0) {
                Loyalty::addPoints($points, (int) $request->input('product_id'), 'product_review', '');
            }

            return back()->with([
                'success'          => 'Komentar je uspješno poslan i bit će vidljiv nakon odobrenja.',
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



        return response()->view('front.layouts.partials.njuskalo', [
            'items' => $njuskalo->items()
        ])->header('Content-Type', 'text/xml');
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

}
