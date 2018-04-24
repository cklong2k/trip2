<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\User;
use App\Content;
use App\Comment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class CommentTest extends DuskTestCase
{
    //use DatabaseMigrations;

    public function setUp()
    {
        parent::setUp();

        $this->publicContentTypes = [
            'blog',
            'buysell',
            'expat',
            'flight',
            'forum',
            'news',
            'shortnews',
            'travelmate',
        ];

        $this->privateContentTypes = [
           'internal',
        ];
    }

    public function test_regular_user_can_add_comment()
    {
        $regular_user = factory(User::class)->create();

        foreach ($this->publicContentTypes as $type) {

            $content = factory(Content::class)->create([
                'user_id' => factory(User::class)->create()->id,
                'type' => $type,
                'end_at' => Carbon::now()->addDays(30),
                'start_at' => Carbon::now()->addDays(30),
            ]);

            $this->browse(function ($browser) use ($regular_user, $content) {
                $browser
                    ->loginAs($regular_user)
                    ->visit("content/$content->type/$content->id")
                    ->type('.EditorComment__body', "Hola chicos de $content->type")
                    ->click('.FormButtonProcess')
                    ->assertSee("Hola chicos de $content->type")
                    ->assertSee($regular_user->name);
            });

            //$comment = Comment::whereBody("Hola chicos de $content->type")->first();

        }
    }
}

/*

actingAs -> loginAs
type <>
press press
pause
assertSee

*/