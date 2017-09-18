<?php

namespace App\Http\Controllers;

use App\Poll;
use App\Image;
use App\Content;
use App\PollField;
use App\PollResult;
use App\Destination;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PollController extends Controller
{
    public function search(Request $request)
    {
        $rules = [
            'start' => 'date_format:Y-m-d|before_or_equal:end',
            'end' => 'date_format:Y-m-d',
        ];

        $this->validate($request, $rules);

        $sess_vars = [
            'poll.table.search.name' => $request->input('poll_name', ''),
            'poll.table.search.start' => $request->input('start', ''),
            'poll.table.search.end' => $request->input('end', ''),
            'poll.table.search.active' => $request->input('poll_active', 2),
        ];

        session($sess_vars);

        return $this->index();
    }

    public function getPollTableHeaderItems()
    {
        $header_names = ['id', 'name', 'start_date', 'end_date'];
        $headers = [];
        $sess_order = session('poll.table.order', '');
        $sess_order_type = session('poll.table.order_type', '');

        foreach ($header_names as $name) {
            if ($sess_order == $name && $sess_order_type == 'desc') {
                $type = 'asc';
            } else {
                $type = 'desc';
            }

            $title = trans('content.poll.table.'.str_replace('_date', '', $name));
            if ($name == 'id') {
                $title = 'ID';
            }

            $headers[] = component('Title')
                            ->is('small')
                            ->with('route', route('poll.index', ['order_by' => $name, 'order_type' => $type]))
                            ->with('title', $title);
        }

        $headers[] = component('Title')
                        ->is('small')
                        ->with('title', trans('content.poll.table.active'));

        return $headers;
    }

    public function getPollTableCellItem($title, $route)
    {
        return component('MetaLink')
                    ->is('large')
                    ->with('route', $route)
                    ->with('title', $title);
    }

    public function index()
    {
        $logged_user = request()->user();

        if (request()->has('empty')) {
            session()->forget('poll.table.search');
        }

        $order_by = request()->input('order_by');
        $order_type = request()->input('order_type');

        $polls = Poll::getLatestPagedItems(50, $order_by, $order_type);

        $items = $this->getPollTableHeaderItems();

        foreach ($polls->items() as $item) {
            $url_name = $item->poll_results_count == 0 ? 'poll.edit' : 'poll.show';
            $route = route($url_name, ['id' => $item->id]);

            $items[] = $this->getPollTableCellItem($item->id, $route);
            $items[] = $this->getPollTableCellItem($item->name, $route);
            $items[] = $this->getPollTableCellItem($item->start_date, $route);
            $items[] = $this->getPollTableCellItem($item->end_date, $route);

            $active = $item->content->status == 1 ? trans('content.poll.table.active') : trans('content.poll.table.active.not');
            $items[] = $this->getPollTableCellItem($active, $route);
        }

        return layout('2col')
            ->with('background', component('BackgroundMap'))
            ->with('color', 'gray')

            ->with('header', region('Header', collect()
                ->push(component('Title')
                    ->is('white')
                    ->with('title', trans('content.poll.index.title'))
                    ->with('route', route('poll.index'))
                )
            ))

            ->with('content', collect()

                ->push(
                    component('Grid5')
                        ->with('items', $items)
                )

                ->push(region('Paginator', $polls))
            )

            ->with('sidebar', collect()
                ->push(component('Button')
                    ->is('large')
                    ->with('title', trans('content.poll.create.title'))
                    ->with('route', route('poll.create'))
                )
                ->push(component('Form')
                    ->with('route', route('poll.search'))
                    ->with('fields', collect()
                        ->push(component('Title')
                            ->is('small')
                            ->with('title', trans('content.poll.table.search'))
                        )
                        ->push(component('FormTextfield')
                            ->with('title', trans('content.poll.edit.name'))
                            ->with('name', 'poll_name')
                            ->with('value', session('poll.table.search.name'))
                        )
                        ->push(component('FormTextfield')
                            ->with('title', trans('content.poll.edit.field.start.title'))
                            ->with('name', 'start')
                            ->with('value', session('poll.table.search.start'))
                        )
                        ->push(component('FormTextfield')
                            ->with('title', trans('content.poll.edit.field.end.title'))
                            ->with('name', 'end')
                            ->with('value', session('poll.table.search.end'))
                        )
                        ->push(component('FormRadio')
                            ->with('options', [
                                ['id' => 1, 'name' => trans('content.poll.table.active')],
                                ['id' => 0, 'name' => trans('content.poll.table.active.not')],
                            ])
                            ->with('name', 'poll_active')
                            ->with('value', session('poll.table.search.active', 2))
                        )
                        ->push(component('FormButton')
                            ->with('title', trans('content.poll.table.search'))
                        )
                        ->push(component('Button')
                            ->is('transparent')
                            ->with('title', trans('content.poll.table.search.empty'))
                            ->with('route', route('poll.index', ['empty' => 1]))
                        )
                    )
                )
            )

            ->with('footer', region('FooterLight'))
            ->render();
    }

    public function create()
    {
        $destinations = Destination::select('id', 'name')->orderBy('name', 'asc')->get();

        $fields = [];
        $old_poll_type = old('poll_type', '');
        if (old('poll_fields', false) && $old_poll_type == 'poll') {
            $poll_fields = old('poll_fields');
            $type_p = explode('_', $poll_fields['select_type']);
            unset($poll_fields['select_type']);

            $fields[] = [
                'field_id' => 0,
                'type' => reset($type_p),
                'options' => [
                    'question' => old('poll_question'),
                    'options' => $poll_fields
                ],
            ];
        }

        return layout('1col')
            ->with('background', component('BackgroundMap'))
                ->with('color', 'gray')

                ->with('header', region('Header', collect()
                    ->push(component('Title')
                        ->is('white')
                        ->with('title', trans('content.poll.index.title'))
                        ->with('route', route('poll.index'))
                    )
                ))
            ->with('content', collect()
                ->push(component('Title')
                    ->with('title', trans('content.poll.create.title'))
                )
                ->push(component('Form')
                    ->with('route', route('poll.store'))
                    ->with('files', true)
                    ->with('fields', collect()
                        ->push(component('FormTextfield')
                            ->with('title', trans('content.poll.edit.name'))
                            ->with('name', 'poll_name')
                            ->with('value', old('poll_name'))
                        )
                        ->push(component('FormTextfield')
                            ->with('title', trans('content.poll.edit.field.start.title'))
                            ->with('name', 'start')
                            ->with('value', old('start'))
                        )
                        ->push(component('FormTextfield')
                            ->with('title', trans('content.poll.edit.field.end.title'))
                            ->with('name', 'end')
                            ->with('value', old('end'))
                        )
                        ->push(component('FormSelect')
                            ->with('name', 'destinations')
                            ->with('options', $destinations)
                            ->with('placeholder', trans('content.index.filter.field.destination.title'))
                        )
                        ->push(component('Title')
                            ->with('title', trans('content.poll.edit.add.field.title'))
                        )
                        ->push(component('PollAddFields')
                            ->with('value', old('poll_type', 'poll'))
                            ->with('fields_json', json_encode($fields, JSON_UNESCAPED_UNICODE))
                            ->with('question_trans', trans('content.poll.edit.question'))
                            ->with('option_trans', trans('content.poll.edit.option'))
                            ->with('poll_trans', trans('content.poll.edit.poll'))
                            ->with('quiz_trans', trans('content.poll.edit.quiz'))
                            ->with('picture_trans', trans('content.poll.edit.fields.picture'))
                            ->with('select_type_trans', trans('content.poll.edit.option.select.type'))
                            ->with('select_one_trans', trans('content.poll.edit.option.select.one'))
                            ->with('select_multiple_trans', trans('content.poll.edit.option.select.multiple'))
                            ->with('answer_options_trans', trans('content.poll.edit.option.answer.options'))
                            ->with('add_option_trans', trans('content.poll.edit.option.add'))
                            ->with('answer_trans', trans('content.poll.answer.noun'))
                            ->with('option_button_trans', trans('content.poll.edit.options'))
                            ->with('textfield_button_trans', trans('content.poll.edit.textfield'))
                        )
                        ->push(component('FormCheckbox')
                            ->with('title', trans('content.poll.create.active'))
                            ->with('name', 'poll_active')
                            ->with('value', old('poll_active'))
                        )
                        ->push(component('FormButton')
                            ->is('large')
                            ->with('title', trans('content.poll.create.title'))
                        )

                    )
                )
            )
            ->with('footer', region('FooterLight'))
            ->render();
    }

    protected function getSaveValidationRules()
    {
        $request = request();

        $rules = [
            'poll_name' => 'required',
            'start' => 'required|date_format:Y-m-d|before_or_equal:end',
            'end' => 'required|date_format:Y-m-d',
            'destinations' => 'required',
            'poll_type' => 'required|in:poll,quiz',
        ];

        if ($request->poll_type == 'poll') {
            $rules['poll_question'] = 'required';
            $rules['poll_fields.*'] = 'required';
            $rules['poll_fields'] = 'min:2';
            $rules['poll_fields.select_type'] = 'required';
        } else {
            $rules['quiz_question'] = 'required|min:1';
            $rules['quiz_question.*.type'] = 'required|in:options,textareafield';
            $rules['quiz_question.*.question'] = 'required';
            $rules['quiz_question.*.answer'] = 'required';

            if ($request->has('quiz_question')) {
                foreach ($request->quiz_question as $index => $arr) {
                    if ($arr['type'] == 'options') {
                        $rules['quiz_question.'.$index.'.options.*'] = 'required';
                        $rules['quiz_question.'.$index.'.options'] = 'required|min:2';
                        $rules['quiz_question.'.$index.'.options.select_type'] = 'required';
                        $rules['quiz_question.'.$index.'.answer'] = 'required|min:1';
                    }
                }
            }
        }

        return $rules;
    }

    public function store(Request $request)
    {
        $rules = $this->getSaveValidationRules();

        $this->validate(request(), $rules);

        $logged_user = $request->user();
        $poll_type = $request->poll_type;

        $content = $logged_user->contents()->create([
            'title' => $request->poll_name,
            'type' => 'poll',
            'status' => $request->has('poll_active') ? 1 : 0,
        ]);

        $content->destinations()->attach(request()->destinations);

        $poll = $content->poll()->create([
            'name' => $request->poll_name,
            'start_date' => $request->start,
            'end_date' => $request->end,
            'type' => $poll_type,
        ]);

        $poll->id = $content->id;

        if ($poll_type == 'poll') {
            $this->addPollFields($poll);
        } elseif ($poll_type == 'quiz') {
            $this->addQuizFields($poll);
        }

        return redirect()
            ->route('poll.index');
    }

    protected function addPollFields(Poll $poll)
    {
        $request = request();

        $options = $request->input('poll_fields');
        $type_p = explode('_', $options['select_type']);
        $type = reset($type_p);
        unset($options['select_type']);

        $options = [
            'question' => $request->poll_question,
            'options' => $options,
        ];

        if ($request->hasFile('poll_photo')) {
            $filename = Image::storeImageFile($request->file('poll_photo'));
            $image = Image::create(['filename' => $filename]);
            $options['image_id'] = $image->id;
        } elseif ($request->has('old_poll_photo')) {
            $options['image_id'] = $request->old_poll_photo;
        }

        $poll->poll_fields()->create([
            'type' => $type,
            'options' => json_encode($options),
        ]);
    }

    protected function addQuizFields(Poll $poll)
    {
        $request = request();
        $fields = [];

        foreach ($request->quiz_question as $index => $question) {
            $options = [
                'question' => $question['question'],
                'answer' => $question['answer'],
            ];

            if (is_array($question['answer'])) {
                $options['answer'] = array_keys($question['answer']);
            }

            $photo_field = 'quiz_photo_'.$index;
            if ($request->hasFile($photo_field)) {
                $filename = Image::storeImageFile($request->file($photo_field));
                $image = Image::create(['filename' => $filename]);
                $options['image_id'] = $image->id;
            } elseif ($request->has('old_'.$photo_field)) {
                $old_photo_field = 'old_'.$photo_field;
                $options['image_id'] = $request->$old_photo_field;
            }

            $type = $question['type'];
            if ($type == 'options') {
                $opts = $question['options'];
                $type_p = explode('_', $opts['select_type']);
                $type = reset($type_p);
                unset($opts['select_type']);

                $options['options'] = $opts;
            } else {
                $type = 'text';
            }

            $fields[] = [
                'type' => $type,
                'options' => json_encode($options),
            ];
        }

        $poll->poll_fields()->createMany($fields);
    }

    protected function makeTextResultGrid(&$parsed_results)
    {
        $grid_items = collect();

        foreach($parsed_results as $index => $result) {
            if ($result['value'] < 10) {
                $grid_items->push(component('MetaLink')->is('smaller')->with('title', $result['title']));
                $grid_items->push(component('MetaLink')->is('smaller')->with('title', $result['value'].'%'));

                unset($parsed_results[$index]);
            }
        }

        if ($grid_items->count() >= 10) {
            $grid_items->prepend(component('Title')->is('smaller')->with('title', 'Vastus'));
            $grid_items->prepend(component('Title')->is('smaller')->with('title', 'Protsent'));
        }

        if ($grid_items->isNotEmpty()) {
            $grid_items->prepend(component('Title')->is('smaller')->with('title', 'Vastus'));
            $grid_items->prepend(component('Title')->is('smaller')->with('title', 'Protsent'));
        }

        return $grid_items;
    }

    public function show($id)
    {
        $poll = Poll::getPollById($id);

        $total_people_ans = $poll->poll_results_count / $poll->poll_fields_count;

        $content = collect()
            ->push(component('Title')
                ->with('title', $poll->name)
            )
            ->push(
                component('Title')
                    ->is('small')
                    ->with('title', trans('content.poll.show.user.count', ['count' => $total_people_ans]))
            );

        if ($total_people_ans > 0) {
            foreach ($poll->poll_fields->getIterator() as $index => $field) {
                $options = json_decode($field->options, true);
                $question = $options['question'];
                $type = $field->type;

                $content->push(
                    component('Title')
                        ->is('small')
                        ->with('title', ($index + 1).'. '.$question)
                );

                $parsed_results = $field->getParsedResults();

                if ($type == 'text') {
                    $grid_items = $this->makeTextResultGrid($parsed_results);
                }

                $content->push(
                    component('Barchart')->with('items', $parsed_results)
                );

                if (isset($grid_items) && $grid_items->isNotEmpty()) {
                    $component = $grid_items->count() >= 14 ? 'Grid4' : 'Grid2';

                    $content->push(
                        component($component)
                            ->with('items', $grid_items)
                    );
                }
            }
        }

        return layout('1col')
            ->with('background', component('BackgroundMap'))
                ->with('color', 'gray')

                ->with('header', region('Header', collect()
                    ->push(component('Title')
                        ->is('white')
                        ->with('title', trans('content.poll.show.title'))
                    )
                ))
            ->with('content', $content)
            ->with('footer', region('FooterLight'))
            ->render();
    }

    public function edit($id)
    {
        $destinations = Destination::select('id', 'name')->orderBy('name', 'asc')->get();

        $poll = Poll::getPollById($id);

        $content_rels = $poll->content->getRelations();
        $destinations = $content_rels['destinations'];
        $destination_id = $destinations->first()->id;

        $poll_fields = [];

        foreach ($poll->poll_fields->all() as $field) {
            $options = json_decode($field->options, true);

            $poll_field = [
                'type' => $field->type,
                'field_id' => $field->field_id,
                'options' => $options,
            ];

            if (isset($options['image_id'])) {
                $image = Image::findOrFail($options['image_id']);
                $poll_field['image_small'] = $image->preset('xsmall_square');
                $poll_field['image_large'] = $image->preset('large');
                $poll_field['image_id'] = $options['image_id'];
            }

            $poll_fields[] = $poll_field;
        }

        return layout('1col')
            ->with('background', component('BackgroundMap'))
                ->with('color', 'gray')

                ->with('header', region('Header', collect()
                    ->push(component('Title')
                        ->is('white')
                        ->with('title', trans('content.poll.index.title'))
                        ->with('route', route('poll.index'))
                    )
                ))
            ->with('content', collect()
                ->push(component('Title')
                    ->with('title', trans('content.poll.edit.title'))
                )
                ->push(component('Form')
                    ->with('route', route('poll.update', ['id' => $poll->id]))
                    ->with('files', true)
                    ->with('fields', collect()
                        ->push(component('FormTextfield')
                            ->with('title', trans('content.poll.edit.name'))
                            ->with('name', 'poll_name')
                            ->with('value', $poll->name)
                        )
                        ->push(component('FormTextfield')
                            ->with('title', trans('content.poll.edit.field.start.title'))
                            ->with('name', 'start')
                            ->with('value', $poll->start_date)
                        )
                        ->push(component('FormTextfield')
                            ->with('title', trans('content.poll.edit.field.end.title'))
                            ->with('name', 'end')
                            ->with('value', $poll->end_date)
                        )
                        ->push(component('FormSelect')
                            ->with('name', 'destinations')
                            ->with('options', $destinations)
                            ->with('placeholder', trans('content.index.filter.field.destination.title'))
                            ->with('value', $destination_id)
                        )
                        ->push(component('Title')
                            ->with('title', trans('content.poll.edit.add.field.title'))
                        )
                        ->push(component('PollAddFields')
                            ->with('value', $poll->type)
                            ->with('fields_json', json_encode($poll_fields, JSON_UNESCAPED_UNICODE))
                            ->with('question_trans', trans('content.poll.edit.question'))
                            ->with('option_trans', trans('content.poll.edit.option'))
                            ->with('poll_trans', trans('content.poll.edit.poll'))
                            ->with('quiz_trans', trans('content.poll.edit.quiz'))
                            ->with('picture_trans', trans('content.poll.edit.fields.picture'))
                            ->with('select_type_trans', trans('content.poll.edit.option.select.type'))
                            ->with('select_one_trans', trans('content.poll.edit.option.select.one'))
                            ->with('select_multiple_trans', trans('content.poll.edit.option.select.multiple'))
                            ->with('answer_options_trans', trans('content.poll.edit.option.answer.options'))
                            ->with('add_option_trans', trans('content.poll.edit.option.add'))
                            ->with('answer_trans', trans('content.poll.answer.noun'))
                            ->with('option_button_trans', trans('content.poll.edit.options'))
                            ->with('textfield_button_trans', trans('content.poll.edit.textfield'))
                        )
                        ->push(component('FormCheckbox')
                            ->with('title', trans('content.poll.create.active'))
                            ->with('name', 'poll_active')
                            ->with('value', $poll->content->status)
                        )
                        ->push(component('FormButton')
                            ->is('large')
                            ->with('title', trans('content.poll.edit.title'))
                        )

                    )
                )
            )
            ->with('footer', region('FooterLight'))
            ->render();
    }

    public function update(Request $request, $id)
    {
        $rules = $this->getSaveValidationRules();

        $this->validate(request(), $rules);

        $poll_type = $request->poll_type;

        $content = Content::findOrFail($id);
        $poll = Poll::findOrFail($id);

        $content->fill([
            'title' => $request->poll_name,
            'type' => 'poll',
            'status' => $request->has('poll_active') ? 1 : 0,
        ])
        ->save();

        $poll->fill([
            'name' => $request->poll_name,
            'start_date' => $request->start,
            'end_date' => $request->end,
            'type' => $poll_type,
        ])
        ->save();

        PollField::where('poll_id', $id)->delete();

        if ($poll_type == 'poll') {
            $this->addPollFields($poll);
        } elseif ($poll_type == 'quiz') {
            $this->addQuizFields($poll);
        }

        return redirect()
            ->route('poll.index');
    }

    public function answerPoll(Request $request)
    {
        $rules = [
            'id' => 'required',
            'values' => 'required|min:1',
        ];

        $this->validate($request, $rules);

        $poll = Poll::getPollById($request->id);
        $poll_field = $poll->poll_fields->first();
        $logged_user = request()->user();

        $values = $request->values;
        if (! is_array($values)) {
            $values = [$values];
        }

        try {
            PollResult::where('field_id', 30)
                ->where('user_id', $logged_user->id)
                ->firstOrFail();
        } catch (ModelNotFoundException $ex) {
            $poll_result = $poll->poll_results()->create([
                'field_id' => $poll_field->field_id,
                'user_id' => $logged_user->id,
                'result' => json_encode($values),
            ]);
        }

        return $poll_field->getParsedResults();
    }

    public function showQuiz($id)
    {
        $quiz = Poll::getPollById($id);
        $logged_user = request()->user();

        $quiz_result = $quiz->poll_results()->where('user_id', $logged_user->id)->get();

        $content = collect()
            ->push(component('Title')
                ->with('title', $quiz->name)
            );

        if ($quiz_result->isEmpty()) {
            $content->push($this->getQuizAnswerFormComponent($quiz));
        } else {
            $content = $content->merge($this->getQuizAnswerResultComponent($quiz));
        }

        return layout('1col')
            ->with('background', component('BackgroundMap'))
                ->with('color', 'gray')

                ->with('header', region('Header', collect()
                    ->push(component('Title')
                        ->is('white')
                        ->with('title', trans('content.poll.edit.quiz'))
                    )
                ))
            ->with('content', $content)
            ->with('footer', region('FooterLight'))
            ->render();
    }

    public function getQuizAnswerFormComponent(Poll $quiz)
    {
        $fields = collect();

        foreach ($quiz->poll_fields->getIterator() as $index => $field) {
            $options = json_decode($field->options, true);
            $question = $options['question'];
            $type = $field->type;

            $fields->push(
                component('Title')
                    ->is('small')
                    ->with('title', ($index + 1).'. '.$question)
            );

            if (isset($options['image_id'])) {
                $image = Image::findOrFail($options['image_id']);
                $fields->push(
                    component('PhotoCard')
                        ->with('small', $image->preset('large'))
                        ->with('large', $image->preset('large'))
                );
            }

            if ($type == 'text') {
                $fields->push(
                    component('FormTextfield')
                        ->with('name', sprintf('quiz_answer[%d]', $field->field_id))
                        ->with('placeholder', trans('content.poll.answer.noun'))
                );
            } elseif ($type == 'radio') {
                foreach ($options['options'] as $opt) {
                    $fields->push(
                        component('FormRadio')
                            ->with('name', sprintf('quiz_answer[%d]', $field->field_id))
                            ->with('options', [['id' => $opt, 'name' => $opt]])
                    );
                }
            } elseif ($type == 'checkbox') {
                foreach ($options['options'] as $opt) {
                    $fields->push(
                        component('FormCheckbox')
                            ->with('name', sprintf('quiz_answer[%d][%s]', $field->field_id, $opt))
                            ->with('title', $opt)
                    );
                }
            }
        }

        return component('Form')
                    ->with('route', route('quiz.answer', ['id' => $quiz->id]))
                    ->with('fields', $fields
                        ->push(component('FormButton')
                            ->is('large')
                            ->with('title', trans('content.poll.answer'))
                        )
                    );
    }

    public function getQuizAnswerResultComponent(Poll $quiz)
    {
        $fields = collect();

        foreach ($quiz->poll_fields->getIterator() as $index => $field) {
            $options = json_decode($field->options, true);
            $question = $options['question'];
            $type = $field->type;

            $fields->push(
                component('Title')
                    ->is('small')
                    ->with('title', ($index + 1).'. '.$question)
            );

            if (isset($options['image_id'])) {
                $image = Image::findOrFail($options['image_id']);
                $fields->push(
                    component('PhotoCard')
                        ->with('small', $image->preset('large'))
                        ->with('large', $image->preset('large'))
                );
            }

            $poll_result = $field->poll_results()
                ->where('user_id', request()->user()->id)
                ->get()
                ->first();
            $user_answer = json_decode($poll_result->result, true);

            if ($type == 'checkbox' || $type == 'radio') {
                $fields->push(component('QuizOptionRow')
                    ->with('type', $type)
                    ->with('answer', is_array($options['answer']) ? $options['answer'] : [$options['answer']])
                    ->with('user_answer', is_array($user_answer) ? $user_answer : [$user_answer])
                    ->with('options', $options['options'])
                );
            } elseif ($type == 'text') {
                $fields->push(component('QuizTextRow')
                    ->with('answer', mb_strtolower($options['answer']))
                    ->with('user_answer', mb_strtolower($user_answer))
                    ->with('value', $user_answer)
                );
            }
        }

        return $fields;
    }

    public function answerQuiz(Request $request, $id)
    {
        $quiz = Poll::getPollById($id);

        $logged_user = $request->user();
        $results = [];
        $rules = [
            'quiz_answer' => 'required',
            'quiz_answer.*' => 'required'
        ];

        foreach ($quiz->poll_fields->getIterator() as $index => $field) {
            $result = [
                'field_id' => $field->field_id,
                'user_id' => $logged_user->id
            ];

            if ($field->type == 'checkbox') {
                $rules['quiz_answer.'.$field->field_id] = 'min:1';
                $rules['quiz_answer.'.$field->field_id.'.*'] = 'required';

                $lowercase_asnwer = array_map("mb_strtolower", array_keys($request->input('quiz_answer.'.$field->field_id)));
                $result['result'] = json_encode($lowercase_asnwer);
            } else {
                $answer = $request->input('quiz_answer.'.$field->field_id);

                if (is_array($answer)) {
                    $lowercase_answer = array_map("mb_strtolower", $answer);
                } else {
                    $lowercase_answer = mb_strtolower($answer);
                }

                $result['result'] = json_encode($lowercase_answer);
            }

            $results[] = $result;
        }

        $this->validate(request(), $rules);

        $quiz->poll_results()->createMany($results);

        return redirect()
            ->route('quiz.answer', ['id' => $quiz->id]);
    }
}
