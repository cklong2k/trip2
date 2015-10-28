@extends('layouts.two_column')

@section('title')
    {{ trans("content.$type.index.title") }}
@stop

@section('content.one')

    <div class="utils-border-bottom 
        @if (! $content->status)
            utils-unpublished
        @endif
    ">

    @include('component.row', [
        'profile' => [
            'image' => $content->user->imagePreset(),
            'route' => route('user.show', [$content->user])
        ],
        'title' => $content->title,
        'text' => view('component.content.text', ['content' => $content]),
        'actions' => view('component.actions', ['actions' => $content->getActions()]),
        'extra' => view('component.flags', ['flags' => $content->getFlags()]),
        'body' => $content->body_filtered,
    ])

    </div>

    <div class="utils-border-bottom">    
    
        @include('component.comment.index', ['comments' => $comments])

    </div>

    @if (\Auth::check())

        @include('component.comment.create')

    @endif

@stop
