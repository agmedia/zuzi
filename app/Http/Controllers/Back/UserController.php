<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Front\Loyalty;
use App\Models\Roles\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = (new User())->newQuery();

        if ($request->has('search') && ! empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
        }

        $users = $query->with('details')->paginate(config('settings.pagination.back'));

        return view('back.user.index', compact('users'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('back.user.edit');
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
        $user = new User();


    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param User $user
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        $roles = Role::selectList();
        $points = Loyalty::hasLoyaltyTotal($user->id);



        return view('back.user.edit', compact('user', 'roles', 'points'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param User                     $user
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $updated = $user->validateRequest($request)->edit();

        if ($updated) {
            return redirect()->route('users.edit', ['user' => $updated])->with(['success' => 'Korisnik je uspješno snimljen!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Greška prilikom snimanja.']);
    }


    /**
     * Log the current admin into the storefront as the selected user.
     *
     * @param Request $request
     * @param User    $user
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function impersonate(Request $request, User $user)
    {
        $admin = $request->user();

        if (! $admin || ! $admin->can('*')) {
            abort(403);
        }

        if ($admin->id === $user->id) {
            return redirect()->back()->with(['warning' => 'Već ste prijavljeni kao taj korisnik.']);
        }

        $request->session()->put([
            'impersonator_id'    => $admin->id,
            'impersonator_name'  => $admin->name,
            'impersonator_email' => $admin->email,
        ]);

        Auth::guard('web')->login($user);
        Auth::shouldUse('web');
        $request->setUserResolver(fn () => $user);
        $request->session()->regenerate();
        $this->storePasswordHashInSession($request, $user);

        return redirect()->route('moj-racun')->with([
            'success' => 'Sada pregledavate korisnički račun za ' . $user->email . '.',
        ]);
    }


    /**
     * Restore the original admin login after storefront impersonation.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function stopImpersonating(Request $request)
    {
        $admin_id = $request->session()->get('impersonator_id');

        if (! $admin_id) {
            return redirect()->route('index');
        }

        $admin = User::find($admin_id);

        if (! $admin) {
            Auth::guard('web')->logout();

            $request->session()->forget([
                'impersonator_id',
                'impersonator_name',
                'impersonator_email',
            ]);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(['error' => 'Admin korisnik više ne postoji. Prijavite se ponovno.']);
        }

        Auth::guard('web')->login($admin);
        Auth::shouldUse('web');
        $request->setUserResolver(fn () => $admin);
        $request->session()->forget([
            'impersonator_id',
            'impersonator_name',
            'impersonator_email',
        ]);
        $request->session()->regenerate();
        $this->storePasswordHashInSession($request, $admin);

        return redirect()->route('dashboard')->with(['success' => 'Vraćeni ste u admin račun.']);
    }


    /**
     * Keep Jetstream's session authentication hash in sync after a manual login.
     *
     * @param Request $request
     * @param User    $user
     *
     * @return void
     */
    private function storePasswordHashInSession(Request $request, User $user): void
    {
        $request->session()->put([
            'password_hash_web' => $user->getAuthPassword(),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request) {}
}
