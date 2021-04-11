<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Models\Category;
use Backpack\CRUD\app\Http\Controllers\CrudController;

// VALIDATION: change the requests to match your own file names if you need form validation
use App\Http\Requests\CategoryRequest as StoreRequest;
use App\Http\Requests\CategoryUpdateRequest as UpdateRequest;
use Backpack\CRUD\CrudPanel;

/**
 * Class CategoryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class CategoryCrudController extends CrudController
{
    public function setup()
    {
        /*
        |--------------------------------------------------------------------------
        | CrudPanel Basic Information
        |--------------------------------------------------------------------------
        */
        $this->crud->setModel(Category::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/category');
        $this->crud->setEntityNameStrings('Категория(ю)', 'Категории');

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
                'type' => 'category-link',
            ],
            [
                'name' => "articlesCount",
                'label' => "Статьи",
                'type' => "model_function",
                'function_name' => 'getArticlesCount',
            ]
        ]);

        //FIELDS
        $this->crud->addField([
            'name' => 'name',
            'label' => "Название категории",
            'type' => 'text'
        ]);


        //$this->crud->setFromDb();

        // add asterisk for fields that are required in CategoryRequest
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
}
