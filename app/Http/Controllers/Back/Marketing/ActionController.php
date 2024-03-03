<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Helpers\Currency;
use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Marketing\Action;
use App\Models\Back\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActionController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $groups = Settings::get('action', 'group_list');
        $query  = Action::query();

        if ($request->has('lock')) {
            $query->where('lock', $request->input('lock') == 'da' ? 1 : 0);
        }
        if ($request->has('coupon')) {
            if ($request->input('coupon') == 'da') {
                $query->where('coupon', '!=', '');
            } else {
                $query->where('coupon', null);
            }
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status') == 'da' ? 1 : 0);
        }
        if ($request->has('group')) {
            $query->where('group', $request->input('group'));
        }

        $actions = $query->paginate(config('settings.pagination.back'))->appends(request()->query());

        return view('back.marketing.action.index', compact('actions', 'groups'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $groups = Settings::get('action', 'group_list');
        $types  = Settings::get('action', 'type_list');

        return view('back.marketing.action.edit', compact('groups', 'types'));
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
        $action = new Action();

        $stored = $action->validateRequest($request)->create();

        if ($stored) {
            return redirect()->route('actions.edit', ['action' => $stored])->with(['success' => 'Action was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the action.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Author $author
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Action $action)
    {
        $groups = Settings::get('action', 'group_list');
        $types  = Settings::get('action', 'type_list');

        return view('back.marketing.action.edit', compact('action', 'groups', 'types'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Author                   $author
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Action $action)
    {
        //dd($request->toArray());
        $updated = $action->validateRequest($request)->edit();

        if ($updated) {
            return redirect()->route('actions.edit', ['action' => $updated])->with(['success' => 'Action was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the action.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Action $action)
    {
        $action->deleteProductActions();
        $destroyed = $action->delete();

        if ($destroyed) {
            return redirect()->route('actions')->with(['success' => 'Akcija je uspjšeno izbrisana!']);
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
            $action    = Action::query()->find($request->input('id'));
            $destroyed = $action->delete();

            Log::info($destroyed);

            if ($destroyed) {
                $action->deleteProductActions($request->input('id'));

                return response()->json(['success' => 200]);
            }
        }

        return response()->json(['error' => 300]);
    }
}
