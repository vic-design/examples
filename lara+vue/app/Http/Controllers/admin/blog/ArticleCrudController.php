<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Models\Article;
use App\Models\Category;
use Backpack\CRUD\app\Http\Controllers\CrudController;

// VALIDATION: change the requests to match your own file names if you need form validation
use App\Http\Requests\ArticleRequest as StoreRequest;
use App\Http\Requests\ArticleUpdateRequest as UpdateRequest;
use Backpack\CRUD\CrudPanel;
use Carbon\Carbon;

/**
 * Class ArticleCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ArticleCrudController extends CrudController
{
    public function setup()
    {
        /*
        |--------------------------------------------------------------------------
        | CrudPanel Basic Information
        |--------------------------------------------------------------------------
        */
        $this->crud->setModel(Article::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/article');
        $this->crud->setEntityNameStrings('Стастья(ю)', 'Статьи');

        /*
        |--------------------------------------------------------------------------
        | CrudPanel Configuration
        |--------------------------------------------------------------------------
        */

        //COLUMNS
        $this->crud->addColumns([
            [
                'name' => 'row_number',
                'type' => 'row_number',
                'label' => '#',
                'orderable' => false,
            ],
            [
                'name' => 'name', // The db column name
                'key' => 'name_link',
                'label' => "Название", // Table column heading
                'type' => 'article-link',
            ],
            ['name' => 'category.name', 'label' => "Категория", 'type' => 'text'],
            ['name' => "created_at", 'label' => "Создано", 'type' => "date",],// 'format' => 'l j F Y'],
            ['name' => "published_at", 'label' => "Опубликовано", 'type' => "date"] // 'format' => 'l j F Y']);
        ]);

        //FIELDS
        $this->crud->addFields([
            [
                'label' => "Категория",
                'type' => 'select2',
                'name' => 'category_id', // the db column for the foreign key
                'entity' => 'category', // the method that defines the relationship in your Model
                'attribute' => 'name', // foreign key attribute that is shown to user
                'model' => Category::class, // foreign key model
            ],
            [
                'name' => 'name',
                'label' => "Название статьи",
                'type' => 'text'
            ],
            [
                'label' => "Изображение",
                'name' => "img",
                'type' => 'image',
                'upload' => true,
                'crop' => true, // set to true to allow cropping, false to disable
                'aspect_ratio' => 1, // ommit or set to 0 to allow any aspect ratio
//                'disk' => 'public_path', // in case you need to show images from a different disk
                // 'prefix' => 'uploads/images/profile_pictures/' // in case your db value is only the file name (no path), you can use this to prepend your path to the image src (in HTML), before it's shown to the user;
            ],
            [
                'name' => 'text',
                'label' => 'Текст',
                'type' => 'ckeditor',
                'options' => [
                    'autoGrow_minHeight' => 200,
                    'autoGrow_bottomSpace' => 50,
                    'removePlugins' => 'maximize',
                ]
            ]
        ]);

        //FILTERS
        $this->crud->addFilter([
            'name' => 'category_id',
            'type' => 'select2',
            'label' => 'Категория'
        ], function () {
            return Category::all()->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'category_id', $value);
        });
        $this->crud->addFilter([
            'type' => 'date_range',
            'name' => 'from_to',
            'label' => 'Период'
        ], false, function ($value) {
            $dates = json_decode($value);
            $this->crud->addClause('where', 'created_at', '>=', $dates->from);
            $this->crud->addClause('where', 'created_at', '<=', $dates->to . ' 23:59:59');
        });

        //BUTTONS
        $this->crud->addButtonFromView('line', 'publish', 'article-publish', 'beginning');

        //VIEWS
//        $this->crud->setCreateView('backpack::crud.article', $this->data);;

//        $this->crud->setFromDb();
        // add asterisk for fields that are required in ArticleRequest
        $this->crud->setRequiredFields(StoreRequest::class, 'create');
        $this->crud->setRequiredFields(UpdateRequest::class, 'edit');
    }

    public function store(StoreRequest $request)
    {
        // your additional operations before save here
        $redirect_location = parent::storeCrud($request);
        // your additional operations after save here
        // use $this->data['entry'] or $this->crud->entry
        return $redirect_location;
    }

    public function update(UpdateRequest $request)
    {
        // your additional operations before save here
        $redirect_location = parent::updateCrud($request);
        // your additional operations after save here
        // use $this->data['entry'] or $this->crud->entry
        return $redirect_location;
    }

    /**
     * @param int $id
     * @return string
     */
    public function publish(int $id)
    {
        Article::where('id', $id)->update(['published_at' => Carbon::now()]);
        return redirect(url($this->crud->route));
    }

    /**
     * @param int $id
     * @return string
     */
    public function unpublish(int $id)
    {
        Article::where('id', $id)->update(['published_at' => null]);
        return redirect(url($this->crud->route));
    }


}
