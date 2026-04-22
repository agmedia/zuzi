<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\Blog;
use App\Models\BlogCtaBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Blog::query()
            ->where('group', 'blog')
            ->orderByDesc('publish_date')
            ->orderByDesc('created_at');

        if ($request->has('search') && ! empty($request->search)) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $blogs = $query->paginate(12);

        return view('back.marketing.blog.index', compact('blogs'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $reusableCtaBlocks = $this->reusableCtaBlocks();

        return view('back.marketing.blog.edit', compact('reusableCtaBlocks'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $blog = new Blog();

        $stored = $blog->validateRequest($request)->create();

        if ($stored) {
            $blog->resolveImage($stored);

            $flash = ['success' => 'Blog was succesfully saved!'];

            if ($warning = $blog->ctaWarningMessage()) {
                $flash['warning'] = $warning;
            }

            return redirect()->route('blogs.edit', ['blog' => $stored])->with($flash);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the blog.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Author $author
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Blog $blog)
    {
        $blog->load('ctaBlocks.buttons');
        $reusableCtaBlocks = $this->reusableCtaBlocks($blog);

        return view('back.marketing.blog.edit', compact('blog', 'reusableCtaBlocks'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Author                   $author
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Blog $blog)
    {
        $updated = $blog->validateRequest($request)->edit();

        if ($updated) {
            $blog->resolveImage($updated);

            $flash = ['success' => 'Blog was succesfully saved!'];

            if ($warning = $blog->ctaWarningMessage()) {
                $flash['warning'] = $warning;
            }

            return redirect()->route('blogs.edit', ['blog' => $updated])->with($flash);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the blog.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Blog $blog)
    {
        $destroyed = Blog::destroy($blog->id);

        if ($destroyed) {
            return redirect()->route('blogs')->with(['success' => 'Blog was succesfully deleted!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error deleting the blog.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroyApi(Request $request)
    {
        if ($request->has('id')) {
            $destroyed = Blog::destroy($request->input('id'));

            if ($destroyed) {
                return response()->json(['success' => 200]);
            }
        }

        return response()->json(['error' => 300]);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadBlogImage(Request $request)
    {
        if ( ! $request->hasFile('upload')) {
            return response()->json(['uploaded' => false]);
        }

        $blog_id = $request->input('blog_id');
        $img = $request->file('upload');
        $name = Str::random(9) . '_' . $img->getClientOriginalName();

        $path = '';

        if ($blog_id) {
            $path = $blog_id . '/';
        }

        Storage::disk('blog')->putFileAs($path, $img, $name);

        return response()->json(['fileName' => $name, 'uploaded' => true, 'url' => url(config('filesystems.disks.blog.url') . $path . $name)]);
    }


    /**
     * Convert a stored CTA block into a payload safe for importing onto another post.
     */
    private function mapReusableCtaBlock(BlogCtaBlock $block): array
    {
        return [
            'id' => $block->id,
            'title' => $block->title,
            'description' => $block->description,
            'sort_order' => (int) $block->sort_order,
            'is_active' => (bool) $block->is_active,
            'buttons' => $block->buttons
                ->sortBy('sort_order')
                ->values()
                ->map(function ($button) {
                    return [
                        'label' => $button->label,
                        'url' => $button->url,
                        'icon' => $button->icon,
                        'style' => $button->style,
                        'sort_order' => (int) $button->sort_order,
                        'is_active' => (bool) $button->is_active,
                    ];
                })
                ->all(),
        ];
    }


    /**
     * Build a small reusable CTA block library for the admin form.
     */
    private function reusableCtaBlocks(?Blog $excludeBlog = null): array
    {
        return BlogCtaBlock::query()
            ->with('buttons')
            ->when($excludeBlog, function ($query) use ($excludeBlog) {
                $query->where('blog_post_id', '!=', $excludeBlog->getKey());
            })
            ->orderBy('title')
            ->orderBy('id')
            ->get()
            ->map(fn (BlogCtaBlock $block) => $this->mapReusableCtaBlock($block))
            ->all();
    }
}
