<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{

    /**
     * Binding of models
     *
     * @var array
     */
    private $models = [
        'Manager',
        'ManagerToken'
    ];


    /**
     * Register services.
     *
     * @return void

     */
    public function register()
    {
        //
        $this->app->bind(
            \App\Repositories\Shop\JobRepositoryInterface::class,
            \App\Repositories\Shop\JobRepository::class,
        );

        $this->app->bind(
            \App\Repositories\Master\IndustryRepositoryInterface::class,
            \App\Repositories\Master\IndustryRepository::class,
        );

        $this->app->bind(
            \App\Repositories\Common\MessageRepositoryInterface::class,
            \App\Repositories\Common\MessageRepository::class,
        );

        $this->app->bind(
            \App\Repositories\Deposit\DepositRepositoryInterface::class,
            \App\Repositories\Deposit\DepositRepository::class,
        );
        $this->app->bind(
            \App\Repositories\Apply\ApplyRepositoryInterface::class,
            \App\Repositories\Apply\ApplyRepository::class,
        );
        $this->app->bind(
            \App\Repositories\Matching\MatchingRepositoryInterface::class,
            \App\Repositories\Matching\MatchingRepository::class,
        );     
        $this->app->bind(
            \App\Repositories\Bank\BankAccountRepositoryInterface::class,
            \App\Repositories\Bank\BankAccountRepository::class,
        );

        $this->app->bind(
            \App\Repositories\Tag\TagRepositoryInterface::class,
            \App\Repositories\Tag\TagRepository::class,
        );

        $this->app->bind(
            \App\Repositories\Shop\ManagerRepositoryInterface::class,
            \App\Repositories\Shop\ManagerRepository::class,
        );
        $this->app->bind(
            \App\Repositories\Ng\NgRepositoryInterface::class,
            \App\Repositories\Ng\NgRepository::class,
        );

        $this->app->bind(
            \App\Repositories\Admin\AdminRepositoryInterface::class,
            \App\Repositories\Admin\AdminRepository::class,
        );


        $this->app->bind(
                \App\Repositories\Interfaces\ManagerRepositoryInterface::class,
                \App\Repositories\Eloquents\ManagerRepository::class,

        );

        $this->app->bind(
                \App\Repositories\Interfaces\ManagerTokenRepositoryInterface::class,
                \App\Repositories\Eloquents\ManagerTokenRepository::class,

        );

        $this->app->bind(
                \App\Repositories\Interfaces\UserRepositoryInterface::class,
                \App\Repositories\Eloquents\UserRepository::class,

        );

        $this->app->bind(
                \App\Repositories\Interfaces\UserTokenRepositoryInterface::class,
                \App\Repositories\Eloquents\UserTokenRepository::class,

        );

        $this->app->bind(
            \App\Repositories\Review\ReviewRepositoryInterface::class,
            \App\Repositories\Review\ReviewRepository::class,
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
