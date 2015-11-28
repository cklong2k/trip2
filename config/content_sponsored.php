<?php

return [

    'frontpage' => [

        'show' => true,
        'with' => [],
        'latest' => 'created_at',
        'take' => 5,

    ],

    'index' => [

        'with' => ['destinations', 'topics'],
        'latest' => 'created_at',
        'paginate' => 24,
    ],

    'store' => [

        'status' => 0,

    ],

    'edit' => [

        'fields' => [
            'title' => [
                'type' => 'text',
                'title' => 'Title',
            ],
            'image_id' => [
                'type' => 'image_id',
            ],
            'body' => [
                'type' => 'textarea',
                'title' => 'Body',
                'rows' => 16,
                'large' => true,
            ],
            'destinations' => [
                'type' => 'destinations',
            ],
            'topics' => [
                'type' => 'topics',
            ],
            'url' => [
                'type' => 'url',
                'title' => 'URL',
            ],
            'submit' => [
                'type' => 'submit',
                'title' => 'Add',
            ],
        ],

        'validate' => [

            'title' => 'required',
            'url' => 'url',
            'file' => 'image',

        ],

    ],

];