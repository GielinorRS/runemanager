<?php

namespace App\Traits;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Item;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait CollectionTrait
{
    /**
     * @param  array<Item>  $items
     *
     * @throws Exception
     */
    public function createHiscore(Category $category, string $name, array $items = []): Collection
    {
        try {
            $modelCreated = $this->createModel($category, $name, $items);
        } catch (Exception $e) {
            throw $e;
        }

        try {
            $migrationCreated = $this->createMigration($category, $name, $items);
        } catch (Exception $e) {
            throw $e;
        }

        try {
            $collection = $this->getOrCreateCollection($category, $name);
        } catch (Exception $e) {
            throw $e;
        }

        try {
            $this->createImageDirectory($category, $collection);
        } catch (Exception $e) {
            throw $e;
        }

        return $collection;
    }

    /**
     * @param  array<Item>  $items
     *
     * @throws Exception
     */
    public function createModel(Category $category, string $name, array $items = []): bool
    {
        $modelName = $this->formatModelName($name);

        if (! File::exists('app/Models/'.Str::ucfirst(Str::studly($category->slug)))) {
            File::makeDirectory('app/Models/'.Str::ucfirst(Str::studly($category->slug)), 0755, true, true);
        }

        try {
            if (class_exists(sprintf("App\Models\%s\%s", Str::studly($category->slug), $this->formatModelName($name)))) {
                return false;
            }
        } catch (Exception $e) {
            // Don't do anything since this would be thrown as the model is not loaded in composer autoload
        }

        try {
            $model = sprintf('%s/%s', Str::studly($category->slug), $modelName);
            $tableName = Str::snake($modelName);

            $namespace = 'namespace App\Models\\'.Str::studly($category->slug).';';
            $table = '$table';
            $fillable = '$fillable';
            $hidden = '$hidden';
            $thisBelongsTo = '$this->belongsTo(Account::class)';

            $modelFileContent = <<<EOD
            <?php

            $namespace

            use App\Models\Account;
            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\Relations\BelongsTo;

            class $modelName extends Model
            {
                protected $table = '$tableName';

                protected $fillable = [
                    'obtained',
                    'kill_count',\r\n
            EOD;
            foreach ($items as $item) {
                //                $fillable = str_replace("'", "", str_replace("-", "_", Str::snake(strtolower($unique))));
                $fillable = $item['id'];

                $modelFileContent .= <<<EOD
                        '$fillable',\r\n
                EOD;
            }
            $modelFileContent .= <<<EOD
                ];

                public function account(): BelongsTo
                {
                    return $thisBelongsTo;
                }
            }
            EOD;

            File::put('app/Models/'.$model.'.php', $modelFileContent);

            return true;
        } catch (Exception $e) {
            throw new Exception(sprintf("Could not create model: '%s'. Message: %s", $modelName, $e->getMessage()));
        }
    }

    /**
     * @throws Exception
     */
    public function createMigration(Category $category, string $name, array $items = []): bool
    {
        try {
            if (! class_exists(sprintf("App\Models\%s\%s", Str::studly($category->slug), $this->formatModelName($name)))) {
                throw new Exception(sprintf("Could not create migration: Model '%s' does not exist.", $this->formatModelName($name)));
            }
        } catch (Exception $e) {
            throw new Exception(sprintf('Could not create migration: %s.', $e->getMessage()));
        }

        $tableName = $this->formatMigrationName($name);
        $migrationName = 'create_'.$tableName.'_table';
        $migrationPath = database_path('migrations');
        $files = scandir($migrationPath);

        foreach ($files as $file) {
            if (strpos($file, $migrationName) !== false) {
                return true;
            }
        }

        $className = 'Create'.Str::studly($this->formatMigrationName($name)).'Table';

        try {
            $migrationFileContent = <<<EOD
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            class $className extends Migration
            {
                /**
                 * Run the migrations.
                 *
                 * @return void
                 */
                public function up(): void
                {
                    Schema::create('$tableName', function (Blueprint \$table) {
                        \$table->id();
                        \$table->unsignedBigInteger('account_id');
                        \$table->integer('rank')->default(0);
                        \$table->integer('kill_count')->default(0);
                        \$table->integer('obtained')->default(0);\r\n
            EOD;
            foreach ($items as $item) {
                $unique = $item['unique'];

                $migrationFileContent .= <<<EOD
                        \$table->integer('$unique')->default(0);\r\n
                EOD;
            }
            $migrationFileContent .= <<<EOD
                        \$table->timestamps();

                        \$table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                    });
                }

                /**
                 * Reverse the migrations.
                 *
                 * @return void
                 */
                public function down(): void
                {
                    Schema::dropIfExists('$tableName');
                }
            };
            EOD;

            File::put('database/migrations/'.date('Y_m_d_His').'_'.$migrationName.'.php', $migrationFileContent);

            // This is to let the timestamp prefix in the migration file name to be unique
            sleep(1);

            return true;
        } catch (Exception $e) {
            throw new Exception(sprintf("Could not create migration: '%s'. Message: %s", $migrationName, $e->getMessage()));
        }
    }

    /**
     * @throws Exception
     */
    public function getOrCreateCollection(Category $category, string $name, bool $skipModelCheck = false): Collection
    {
        if (! $skipModelCheck && ! class_exists(sprintf("App\Models\%s\%s", Str::studly($category->slug), $this->formatModelName($name)))) {
            throw new Exception(sprintf("Could not create collection: Model '%s'does not exist:.", $this->formatModelName($name)));
        }

        $collection = Collection::whereCategoryId($category->id)->whereName($name)->first();

        if ($collection) {
            return $collection;
        }

        $newestCollection = Collection::whereCategoryId($category->id)->orderByDesc('order')->pluck('order')->first();

        if ($newestCollection) {
            $order = ++$newestCollection;
        } else {
            $order = $category->id * 1000;
        }

        try {
            $collection = new Collection;

            $collection->category_id = $category->id;
            $collection->order = $order;
            $collection->name = $name;
            $collection->slug = Str::slug($name);
            $collection->model = sprintf('App\\Models\\%s\\%s', Str::studly($category->slug), $this->formatModelName($name));

            $collection->save();

            return $collection;
        } catch (Exception $e) {
            throw new Exception(sprintf("Could not create collection: '%s'. Message: %s", $name, $e->getMessage()));
        }
    }

    /**
     * @throws Exception
     */
    public function createImageDirectory(Category $category, Collection $collection): void
    {
        try {
            $imageDirectoryPath = sprintf('%s/images/%s/%s', public_path(), $category->slug, $collection->slug);

            if (! File::exists($imageDirectoryPath)) {
                File::makeDirectory($imageDirectoryPath, 0755, true, true);
            }
        } catch (Exception $e) {
            throw new Exception(sprintf("Could not create image directory: '%s'. Message: %s", $collection->slug, $e->getMessage()));
        }
    }

    private function formatModelName(string $name): string
    {
        return Str::studly(Str::slug($name));
    }

    private function formatMigrationName(string $name): string
    {
        return Str::snake(Str::slug($name, '_'));
    }
}
