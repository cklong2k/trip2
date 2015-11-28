From {{ $user_from->name }}:

{!! $new_message->body !!}

{{ trans('message.store.email.body', [
    'url' => route('message.index.with', [
        $user_to->id,
        $user_from->id,
        '#message-' . $new_message->id
    ])
]) }}

---

{{ config('site.name') }}