<?php

namespace Encore\Admin\Middleware;

use Encore\Admin\Auth\Database\OperationLog as OperationLogModel;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LogOperation
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        if ($this->shouldLogOperation($request)) {
            $path = substr($request->path(), 0, 255);

            $routeMap = config('admin.auth.route_name_map', []);

            if (isset($routeMap[$request->method()])) {
                foreach ($routeMap[$request->method()] as $routePath => $routeName) {
                    if (strpos($request->path(), $routePath) !== false) {
                        if (is_array($routeName)) {
                            foreach($routeName as $inputValue => $subName) {
                                foreach ($request->input() as $key => $value) {
                                    if (strpos($value, $inputValue) !== false) {
                                        $path = $subName;
                                    }
                                }
                            }
                        } else {
                            $path = $routeName;
                        }
                    }
                }
            }

            $input = $request->input();

            if (!empty($input['password'])) {
                $input['password'] = '********';
            }

            if (!empty($input['password_confirmation'])) {
                $input['password_confirmation'] = '********';
            }

            $log = [
                'user_id' => Admin::user()->id,
                'path'    => $path,
                'method'  => $request->method(),
                'ip'      => $request->getClientIp(),
                'input'   => json_encode($input),
            ];

            try {
                OperationLogModel::create($log);
            } catch (\Exception $exception) {
                // pass
            }
        }

        return $next($request);
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    protected function shouldLogOperation(Request $request)
    {
        return config('admin.operation_log.enable')
            && !$this->inExceptArray($request)
            && $this->inAllowedMethods($request->method())
            && Admin::user();
    }

    /**
     * Whether requests using this method are allowed to be logged.
     *
     * @param string $method
     *
     * @return bool
     */
    protected function inAllowedMethods($method)
    {
        $allowedMethods = collect(config('admin.operation_log.allowed_methods'))->filter();

        if ($allowedMethods->isEmpty()) {
            return true;
        }

        return $allowedMethods->map(function ($method) {
            return strtoupper($method);
        })->contains($method);
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    protected function inExceptArray($request)
    {
        foreach (config('admin.operation_log.except') as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            $methods = [];

            if (Str::contains($except, ':')) {
                list($methods, $except) = explode(':', $except);
                $methods = explode(',', $methods);
            }

            $methods = array_map('strtoupper', $methods);

            if ($request->is($except) &&
                (empty($methods) || in_array($request->method(), $methods))) {
                return true;
            }
        }

        return false;
    }
}
