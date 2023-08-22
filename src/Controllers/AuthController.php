<?php

namespace Encore\Admin\Controllers;

use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Auth\Database\PasswordResetToken;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Layout\Content;
use Encore\Admin\Emails\ForgotPassword;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Email;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * @var string
     */
    protected $loginView = 'admin::login';

    /**
     * Show the login page.
     *
     * @return \Illuminate\Contracts\View\Factory|Redirect|\Illuminate\View\View
     */
    public function getLogin()
    {
        if ($this->guard()->check()) {
            return redirect($this->redirectPath());
        }

        return view($this->loginView);
    }

    /**
     * Handle a login request.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function postLogin(Request $request)
    {
        $this->loginValidator($request->all())->validate();

        $credentials = $request->only([$this->username(), 'password']);
        $remember = $request->get('remember', false);

        if ($this->guard()->attempt($credentials, $remember)) {
            return $this->sendLoginResponse($request);
        }

        return back()->withInput()->withErrors([
            $this->username() => $this->getFailedLoginMessage(),
        ]);
    }

    /**
     * Show the forgot password page.
     *
     * @return \Illuminate\Contracts\View\Factory|Redirect|\Illuminate\View\View
     */
    public function getForgotPassword()
    {
        if ($this->guard()->check()) {
            return redirect($this->redirectPath());
        }

        return view('admin::forgot_password');
    }

    /**
     * Handle the forgot password request.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function postForgotPassword(Request $request)
    {
        $this->forgotPasswordValidator($request->all())->validate();

        $email = $request->input($this->username());

        if (Administrator::where('username', $email)->exists()) {
            $token = Str::random(8);

            PasswordResetToken::create([
                'email' => $email,
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Mail::to($email)->send(new ForgotPassword($email, $token));

            return $this->sendForgotPasswordResponse($request);
        }

        return back()->withInput()->withErrors([
            $this->username() => 'Email does not match our records.',
        ]);
    }

    /**
     * Show the reset password page.
     *
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|Redirect|\Illuminate\View\View
     */
    public function getResetPassword(Request $request)
    {
        if ($this->guard()->check()) {
            return redirect($this->redirectPath());
        }

        $email = $request->input('email');
        $token = $request->input('token');

        $validToken = PasswordResetToken::where('email', '=' , $email)
            ->where('token', '=', $token)
            ->where('created_at', '>', DB::raw('date_sub(now(), INTERVAL 1 DAY)'))
            ->exists();

        return view('admin::reset_password', compact('validToken', 'token', 'email'));
    }

    /**
     * Handle the reset password request.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function postResetPassword(Request $request)
    {
        $this->resetPasswordValidator($request->all())->validate();

        $email = $request->input('email');
        $token = $request->input('token');
        $password = $request->input('password');

        $validToken = PasswordResetToken::where('email', '=' , $email)
            ->where('token', '=', $token)
            ->where('created_at', '>', DB::raw('date_sub(now(), INTERVAL 1 DAY)'))
            ->exists();

        if (!$validToken) {
            return view('admin::reset_password', compact('validToken', 'token', 'email'));
        }

        $userModel = config('admin.database.users_model');

        $user = $userModel::query()->where('username', $email)->first();

        $user->password = Hash::make($password);

        $user->save();

        admin_toastr('You have reset the password successfully.');

        return back();
    }

    /**
     * Get a validator for an incoming login request.
     *
     * @param array $data
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function loginValidator(array $data)
    {
        return Validator::make($data, [
            $this->username()   => 'required',
            'password'          => 'required',
        ]);
    }

    /**
     * Get a validator for an incoming forgot password request.
     *
     * @param array $data
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function forgotPasswordValidator(array $data)
    {
        return Validator::make($data, [
            $this->username()   => 'required',
        ]);
    }

    /**
     * Get a validator for an incoming reset password request.
     *
     * @param array $data
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function resetPasswordValidator(array $data)
    {
        return Validator::make($data, [
            'password'         => 'required',
            'password_confirm' => 'required|same:password'
        ]);
    }

    /**
     * User logout.
     *
     * @return Redirect
     */
    public function getLogout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();

        return redirect(config('admin.route.prefix'));
    }

    /**
     * User setting page.
     *
     * @param Content $content
     *
     * @return Content
     */
    public function getSetting(Content $content)
    {
        $form = $this->settingForm();
        $form->tools(
            function (Form\Tools $tools) {
                $tools->disableList();
                $tools->disableDelete();
                $tools->disableView();
            }
        );

        return $content
            ->title(trans('admin.user_setting'))
            ->body($form->edit(Admin::user()->id));
    }

    /**
     * Update user setting.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function putSetting()
    {
        return $this->settingForm()->update(Admin::user()->id);
    }

    /**
     * Model-form for user setting.
     *
     * @return Form
     */
    protected function settingForm()
    {
        $class = config('admin.database.users_model');

        $form = new Form(new $class());

        $form->display('username', trans('admin.username'));
        $form->text('name', trans('admin.name'))->rules('required');
        $form->image('avatar', trans('admin.avatar'));
        $form->password('password', trans('admin.password'))->rules('confirmed|required');
        $form->password('password_confirmation', trans('admin.password_confirmation'))->rules('required')
            ->default(function ($form) {
                return $form->model()->password;
            });

        $form->setAction(admin_url('auth/setting'));

        $form->ignore(['password_confirmation']);

        $form->saving(function (Form $form) {
            if ($form->password && $form->model()->password != $form->password) {
                $form->password = Hash::make($form->password);
            }
        });

        $form->saved(function () {
            admin_toastr(trans('admin.update_succeeded'));

            return redirect(admin_url('auth/setting'));
        });

        return $form;
    }

    /**
     * @return string|\Symfony\Component\Translation\TranslatorInterface
     */
    protected function getFailedLoginMessage()
    {
        return Lang::has('auth.failed')
            ? trans('auth.failed')
            : 'These credentials do not match our records.';
    }

    /**
     * Get the post login redirect path.
     *
     * @return string
     */
    protected function redirectPath()
    {
        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo();
        }

        return property_exists($this, 'redirectTo') ? $this->redirectTo : config('admin.route.prefix');
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendLoginResponse(Request $request)
    {
        admin_toastr(trans('admin.login_successful'));

        $request->session()->regenerate();

        return redirect()->intended($this->redirectPath());
    }

    /**
     * Send the response after valid forgot password request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendForgotPasswordResponse(Request $request)
    {
        admin_toastr('System has sent the password reset email to you.');

        return back();
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    protected function username()
    {
        return 'username';
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Admin::guard();
    }
}
