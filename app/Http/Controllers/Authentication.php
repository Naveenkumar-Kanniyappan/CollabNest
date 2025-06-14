<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;
use App\Models\Interest;
use App\Models\Profession;
use App\Models\Skill;
use App\Models\UserTag;

class Authentication extends Controller
{
    /**
     * Show the welcome page or redirect to dashboard if authenticated.
     */
    public function welcome()
    {
        return Auth::check() ? redirect('/dashboard') : view('welcome');
    }

    /**
     * Show the register view or redirect if already logged in.
     */
    public function navregister()
    {
        return Auth::check() ? redirect('/dashboard') : view('register');
    }

    /**
     * Show the login view or redirect if already logged in.
     */
    public function navLogin()
    {
        return Auth::check() ? redirect('/dashboard') : view('login');
    }

    /**
     * Handle user registration.
     */
    public function register(Request $request)
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

    

        $request->validate([
            'name'         => 'required',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:6',
            'profession'   => 'required|array|min:1',
            'skills'       => 'required|array|min:1',
            'interests'    => 'required|array|min:1',
            'availability' => 'required|string',
        ]);

        $token = Str::random(40);

        // Create user
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
            'verification_token' => $token,
            'verified_at' => now(),
        ]);

        

        foreach($request->profession as $prof){
            UserTag::create([
                'tag_id'=>Profession::where('profession',$prof)->value('id'),
                'user_id'=>$user->id,
                'tag_model'=>'profession'
            ]);
        }
        foreach($request->skills as $skill){
             UserTag::create([
                'tag_id'=>Skill::where('skill',$skill)->value('id'),
                'user_id'=>$user->id,
                'tag_model'=>'tech_skill'
            ]);
            
        }
        foreach($request->interests as $interest){
             UserTag::create([
                'tag_id'=>Interest::where('interest',$interest)->value('id'),
                'user_id'=>$user->id,
                'tag_model'=>'interest'
            ]);
            
        }

        // Save profile settings
        $settings = [
            'availability'     => $request->availability,
        ];

        UserProfile::create([
            'user_id'          => $user->id,
            'profile_settings' => json_encode($settings),
        ]);
     
    
        Mail::to($user->email)->send(new WelcomeMail($token,$user));
        
        return view('verification-success')->with('user', $user);

        
    }

        public function verify(Request $request)
    {
        $emailHash = $request->query('email_hash');
        $token = $request->query('token');

        $user = User::where('verification_token', $token)->first();

        if (!$user || hash('sha256', $user->email) !== $emailHash) {
            return view('verification-failed', ['message' => 'Invalid or expired token']);
        }

        $user->update([
            'verified_at' => now(),
            'verification_token' => null,
        ]);

        Auth::login($user);

        return redirect('/dashboard')->with('success', 'Register successful');
    }
    public function loginUser(Request $request)
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            return redirect('/dashboard')->with('success', 'Login successful');
        }

        return back()->with('error', 'Invalid credentials')->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function dashboard()
    {
        return Auth::check() ? view('dashboard') : redirect('/login');
    }

}