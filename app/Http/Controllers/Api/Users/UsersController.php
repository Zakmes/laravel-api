<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Transformers\Users\UserTransformer;
use Illuminate\Http\Request;

/**
 * Class UsersController.
 *
 * @author Jose Fonseca <jose@ditecnologia.com>
 */
class UsersController extends Controller
{
    /**
     * @var \App\Models\User
     */
    protected $model;

    /**
     * UsersController constructor.
     *
     * @param \App\Models\User $model
     */
    public function __construct(User $model)
    {
        $this->model = $model;
        $this->middleware('permission:List users')->only('index');
        $this->middleware('permission:List users')->only('show');
        $this->middleware('permission:Create users')->only('store');
        $this->middleware('permission:Update users')->only('update');
        $this->middleware('permission:Delete users')->only('destroy');
    }

    /**
     * List users
     *
     * Returns the Users resource with the roles relation.
     * @group Users
     * @transformerCollection App\Transformers\Users\UserTransformer
     * @transformerModel App\Models\User
     * @transformerPaginator League\Fractal\Pagination\IlluminatePaginatorAdapter 20
     */
    public function index(Request $request)
    {
        $paginator = $this->model->with('roles.permissions')->paginate($request->get('limit', config('app.pagination_limit', 20)));
        if ($request->has('limit')) {
            $paginator->appends('limit', $request->get('limit'));
        }

        return fractal($paginator, new UserTransformer())->respond();
    }

    /**
     * Get single user
     *
     * Returns the User resource by it's uuid
     * @group Users
     * @urlParam uuid string required The UUID of the user.
     * @transformer App\Transformers\Users\UserTransformer
     * @transformerModel App\Models\User
     */
    public function show($id)
    {
        $user = $this->model->with('roles.permissions')->byUuid($id)->firstOrFail();

        return fractal($user, new UserTransformer())->respond();
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);
        $user = $this->model->create($request->all());
        if ($request->has('roles')) {
            $user->syncRoles($request['roles']);
        }

        return fractal($user, new UserTransformer())->respond(201);
    }

    /**
     * @param Request $request
     * @param $uuid
     * @return mixed
     */
    public function update(Request $request, $uuid)
    {
        $user = $this->model->byUuid($uuid)->firstOrFail();
        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$user->id,
        ];
        if ($request->method() == 'PATCH') {
            $rules = [
                'name' => 'sometimes|required',
                'email' => 'sometimes|required|email|unique:users,email,'.$user->id,
            ];
        }
        $this->validate($request, $rules);
        // Except password as we don't want to let the users change a password from this endpoint
        $user->update($request->except('_token', 'password'));
        if ($request->has('roles')) {
            $user->syncRoles($request['roles']);
        }

        return fractal($user->fresh(), new UserTransformer())->respond();
    }

    /**
     * @param Request $request
     * @param $uuid
     * @return mixed
     */
    public function destroy(Request $request, $uuid)
    {
        $user = $this->model->byUuid($uuid)->firstOrFail();
        $user->delete();

        return response()->json(null, 204);
    }
}
