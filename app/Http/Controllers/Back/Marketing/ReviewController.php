<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        $reviews = Review::query()
            ->with('product')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($nested) use ($search) {
                    $nested->where('fname', 'like', '%' . $search . '%')
                        ->orWhere('lname', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('message', 'like', '%' . $search . '%')
                        ->orWhereHas('product', function ($productQuery) use ($search) {
                            $productQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('sku', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($request->filled('status') && in_array($request->input('status'), ['approved', 'pending'], true), function ($query) use ($request) {
                $query->where('status', $request->input('status') === 'approved');
            })
            ->orderBy('status')
            ->orderByDesc('created_at')
            ->paginate(config('settings.pagination.back'))
            ->withQueryString();

        return view('back.marketing.review.index', compact('reviews'));
    }


    /**
     * @param Review $review
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(Review $review)
    {
        $review->load('product');

        return view('back.marketing.review.edit', compact('review'));
    }


    /**
     * @param Request $request
     * @param Review  $review
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Review $review)
    {
        $updated = $review->validateRequest($request)->edit();

        if ($updated) {
            return redirect()
                ->route('reviews.edit', ['review' => $updated])
                ->with(['success' => 'Komentar je uspješno spremljen.']);
        }

        return redirect()->back()->with(['error' => 'Dogodila se greška prilikom spremanja komentara.']);
    }


    /**
     * @param Request $request
     * @param Review  $review
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Review $review)
    {
        if (Review::destroy($review->id)) {
            return redirect()->route('reviews')->with(['success' => 'Komentar je uspješno izbrisan.']);
        }

        return redirect()->back()->with(['error' => 'Dogodila se greška prilikom brisanja komentara.']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyApi(Request $request)
    {
        if ($request->filled('id') && Review::destroy((int) $request->input('id'))) {
            return response()->json(['success' => 200]);
        }

        return response()->json(['error' => 300, 'message' => 'Brisanje komentara nije uspjelo.']);
    }
}
