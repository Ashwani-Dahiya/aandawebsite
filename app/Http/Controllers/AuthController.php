<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\StateModel;
use App\Models\BlogModel;
use App\Models\BrandModel;
use App\Models\CartModel;
use App\Models\ProductModel;
use App\Models\CategorieModel;
use App\Models\HomeLastBannerModel;
use App\Models\HomeMainBannerModel;
use App\Models\HomeMiddleBannerModel;
use App\Models\ReviewModel;
use App\Models\SectionModel;
use Exception;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function home()
    {
        $sections = SectionModel::with('products')->get();
        $count = 0;

        if ($sections->isNotEmpty()) {
            $blog = BlogModel::orderBy("id", "desc")->paginate(4);
            $item = ProductModel::orderBy("id", "desc")->paginate(4);
            $newbrand = BrandModel::orderBy("id", "desc")->paginate(10);
            $review = ReviewModel::orderBy("id", "desc")->paginate(10);
            $categorie = CategorieModel::orderBy("id", "desc")->paginate(10);
            // $count = $this->item_count();
            $banner = HomeMainBannerModel::all();
            $midbanners = HomeMiddleBannerModel::orderBy("id", "asc")->paginate(1);
            $lastbanners = HomeLastBannerModel::orderBy("id", "asc")->paginate(1);

            return view("index", compact("blog", "item", "newbrand", "review", "categorie", "sections", "count", "banner", "midbanners", "lastbanners"));
        } else {
            // Handle the case where no sections are found
            return view("index", [
                'blog' => [],
                'item' => [],
                'newbrand' => [],
                'review' => [],
                'categorie' => [],
                'sections' => [],
                'banner' => [],
                'midbanners' => [],
                'lastbanners' => [],
            ]);
        }
    }
    public function item_count()
    {
        // Check if the user is authenticated
        if (Auth::check()) {
            $rec = CartModel::where('user_id', Auth::user()->id)->count();
            return $rec;
        } else {
            // If the user is not authenticated, return 0 or handle it as needed
            return 0;
        }
    }
    public function update_profile_page()
    {
        $states = StateModel::with('cities')->get();
        $record = User::find(Auth::user()->id);
        return view("profileupdate", compact("states", "record"));
    }
    public function update_profile(Request $request)
    {
        $user = User::find(Auth::user()->id);
        $newdata = $request->all();
        $user->username = $request->first_name;
        $user->update($newdata);
        return redirect()->route('profile.view')->with("success", "Profile Updated Successfully");
    }
    public function profile_page()
    {
        $user = User::find(Auth::user()->id);
        return view("profile", compact("user"));
    }

    public function login_page()
    {

        return view("auth.login");
    }
    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
        if (Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['status' => true, 'msg' => 'Done']);
        }

        return response()->json(['status' => false, 'msg' => 'Please check email or password']);
    }

    public function register_page()
    {
        $states = StateModel::with('cities')->get();
        return view("auth.register", compact("states"));
    }
    public function register(Request $request)
    {
        $this->validate($request, [
            'first_name' => 'required|string|min:2|max:100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|same:password',
        ]);
        User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'username' => $request->first_name,
            'phone' => $request->phone_number,
            'address' => $request->address,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            'post_code' => $request->post_code,
        ]);
        if (Auth::attempt($request->only('email', 'password'))) {
            return redirect()->route('home')->with('success', 'Successfully Logged In');
        }
        return redirect('auth.register')->withError('error');
    }


    public function logout()
    {
        Session::flush();
        Auth::logout();
        return redirect('login')->with('success', 'Logout Successfully');
    }

    public function delete_cart_item($id)
    {
        try {
            // Find the CartModel record by ID and delete it
            CartModel::where('id', $id)->delete();

            // Redirect back with a success message
            return redirect()->back()->with('success', 'Sucess');
        } catch (Exception $e) {
            // Handle any exceptions (errors) that might occur during the deletion
            return redirect()->back()->with('error', 'An error occurred while removing the item from the cart.');
        }
    }
    public function delete(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $authenticatedUser = Auth::user();


        if ($authenticatedUser->email == $request->email && Hash::check($request->password, $authenticatedUser->password)) {
            // Delete the user

            User::find($authenticatedUser->id)->delete();

            // Flush session and logout
            Session::flush();
            Auth::logout();

            return redirect('login')->with('success', 'Account Deleted Successfully');
        } else {
            return redirect()->back()->with('error', 'Check your email or password');
        }
    }
    public function change_password(Request $request)
    {

        $this->validate($request, [
            'currentPassword' => 'required',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|same:password',
        ]);

        $user = Auth::user();

        // Check if the current password provided matches the user's current password
        if (Hash::check($request->currentPassword, $user->password)) {
            // Hash the new password before saving
            $newHashPassword = Hash::make($request->password);

            // Update the user's password using the authenticated user
            $user->password = $newHashPassword;
            $new = User::find(Auth::user()->id);
            $new->update([
                'password' => $newHashPassword,
            ]);
            $new->save();
            Session::flush();
            Auth::logout();
            return redirect()->route('login')->with('success', 'Password Changed Successfully');
        } else {
            // If the current password is incorrect, redirect with an error message
            return redirect()->back()->with('error');
        }
    }

    public function forgot_password_page()
    {
        return view('auth.forgot');
    }
    public function forgot_password(Request $request)
    {
        $this->validate($request, [
            'username' => 'required',
            'email' => 'required|email',
            'phone' => 'required|min:10|max:10',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|same:password',
        ]);

        $user = User::where('username', $request->username)->where('email', $request->email)->where('phone', $request->phone)->first();
        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();
            return redirect()->route('login')->with('success', 'Password Changed Successfully');
        } else {
            return redirect()->back()->with('error', 'Check your username,email or phone');
        }
    }
    public function google_login_page()
    {
        return Socialite::driver('google')->redirect();
    }
    public function handleGoogleCallback()
    {
        try {
            $user = Socialite::driver('google')->user();
            // Check if email is available
            if ($user->email) {
                $email = $user->email;
                $findUser = User::where('email', $email)->first();

                if ($findUser) {
                    Auth::login($findUser);
                    // Log in successful, redirect to home
                    return redirect()->route('home');
                } else {
                    // If user doesn't exist, create a new user
                    $newUser = User::create([
                        'first_name' => $user->name,
                        'email' => $user->email,
                        'password' => Hash::make("123456"),
                        'username' => $user->name,
                        'simple_password' => '123456',
                        'login_via' => "Google"
                    ]);

                    Auth::login($newUser);
                    // New user created and logged in, redirect to home
                    return redirect()->route('home');
                }
            } else {
                // Handle the case where email is not available
                throw new Exception('Email not provided by Google.');
            }
        } catch (Exception $e) {
            dd($e->getMessage());
        }
    }

    public function linkedin_login_page()
    {
        return Socialite::driver('linkedin')->redirect();
    }


    public function handleLinkedInCallback(Request $request)
    {
        $user = Socialite::driver('linkedin')->user();

        dd($user);
    }

    public function facebook_login_page(){
        return Socialite::driver('facebook')->redirect();
    }
    public function handleFacebookCallback(){
        $user = Socialite::driver('facebook')->user();

       dd($user);
    }
}
