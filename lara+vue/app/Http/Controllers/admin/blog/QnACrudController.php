<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Models\Article;
use App\Models\QnA;
use Backpack\CRUD\app\Http\Controllers\CrudController;

// VALIDATION: change the requests to match your own file names if you need form validation
use App\Http\Requests\QnARequest as StoreRequest;
use App\Http\Requests\QnARequest as UpdateRequest;
use Backpack\CRUD\CrudPanel;

/**
 * Class QnACrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class QnACrudController extends CrudController
{
    public function setup()
    {
        /*
        |--------------------------------------------------------------------------
        | CrudPanel Basic Information
        |--------------------------------------------------------------------------
        */
        $this->crud->setModel(QnA::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/qna');
        $this->crud->setEntityNameStrings('Вопрос-ответ', 'Вопросы и ответы');

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
            ['name' => 'article.name', 'label' => "Статья", 'type' => 'text'],
            ['name' => 'question', 'label' => "Вопрос"],
            ['name' => 'answer', 'label' => "Ответ"],
        ]);

        //FIELDS
        $this->crud->addFields([
            [
                'label' => "Статья",
                'type' => 'select2',
                'name' => 'article_id', // the db column for the foreign key
                'entity' => 'article', // the method that defines the relationship in your Model
                'attribute' => 'name', // foreign key attribute that is shown to user
                'model' => Article::class, // foreign key model
            ],
            [
                'name' => 'question',
                'label' => "Вопрос",
                'type' => 'text'
            ],
            [
                'name' => 'answer',
                'label' => "Ответ",
                'type' => 'text'
            ],
        ]);

        //FILTERS
        $this->crud->addFilter([
            'name' => 'article_id',
            'type' => 'select2',
            'label' => 'Статья'
        ], function () {
            return Article::all()->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'article_id', $value);
        });

        // TODO: remove setFromDb() and manually define Fields and Columns
//        $this->crud->setFromDb();

        // add asterisk for fields that are required in QnARequest
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
