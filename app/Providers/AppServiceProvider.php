<?php

namespace App\Providers;

use App\Models\Meeting;
use App\Models\Priority;
use App\Models\Project;
use App\Models\Status;
use App\Models\Tag;
use App\Models\Task;
use App\Observers\MeetingObserver;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use App\Policies\TaxonomyPolicy;
use App\Services\AI\FakeProvider;
use App\Services\AI\OpenAIProvider;
use App\Services\AI\Provider as AIProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AIProvider::class, function () {
            $key = (string) config('services.openai.key', env('OPENAI_API_KEY', ''));
            if ($key === '' || app()->environment('testing')) {
                return new FakeProvider();
            }
            return new OpenAIProvider(
                apiKey: $key,
                model: (string) config('services.openai.model', 'gpt-4o-mini'),
            );
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePolicies();
        $this->configureObservers();
    }

    protected function configureObservers(): void
    {
        Project::observe(ProjectObserver::class);
        Task::observe(TaskObserver::class);
        Meeting::observe(MeetingObserver::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configurePolicies(): void
    {
        Gate::policy(Status::class, TaxonomyPolicy::class);
        Gate::policy(Priority::class, TaxonomyPolicy::class);
        Gate::policy(Tag::class, TaxonomyPolicy::class);
    }
}
