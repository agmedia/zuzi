<?php

namespace App\Http\Controllers\Back\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Publisher;
use Illuminate\Http\Request;

class PublisherController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->has('search') && ! empty($request->search)) {
            $publishers = Publisher::where('title', 'like', '%' . $request->search . '%')->paginate(12)->appends(request()->query());
        } else {
            $publishers = Publisher::paginate(12)->appends(request()->query());
        }

        return view('back.catalog.publisher.index', compact('publishers'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('back.catalog.publisher.edit');
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
        $publisher = new Publisher();

        $stored = $publisher->validateRequest($request)->create();

        if ($stored) {
            $publisher->resolveImage($stored);

            return redirect()->route('publishers.edit', ['publisher' => $stored])->with(['success' => 'Izdavač je uspjšeno snimljen!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Greška prilikom snimanja.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Publisher $publisher
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Publisher $publisher)
    {
        return view('back.catalog.publisher.edit', compact('publisher'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Publisher                $publisher
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Publisher $publisher)
    {
        $updated = $publisher->validateRequest($request)->edit();

        if ($updated) {
            $publisher->resolveImage($updated);

            return redirect()->route('publishers.edit', ['publisher' => $updated])->with(['success' => 'Izdavač je uspjšeno snimljen!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Greška prilikom snimanja.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Publisher $publisher)
    {
        $destroyed = Publisher::destroy($publisher->id);

        if ($destroyed) {
            return redirect()->route('publishers')->with(['success' => 'Izdavač je uspjšeno izbrisan!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Greška prilikom brisanja.']);
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
            $destroyed = Publisher::destroy($request->input('id'));

            if ($destroyed) {
                return response()->json(['success' => 200]);
            }
        }

        return response()->json(['error' => 300]);
    }
}
