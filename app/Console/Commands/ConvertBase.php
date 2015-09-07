<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

use DB;

use Imageconv;

use App;
use App\Content;

class ConvertBase extends Command
{

    protected $connection = 'trip';

    protected $take;
    protected $copyFiles;

    protected $chunk = 10;

    protected $imageQuality = 75;

    protected $client;

    protected $contentTypes = [
        'story',
        'trip_blog',
        'trip_forum',
        'trip_forum_other',
        'trip_forum_expat',
        'trip_forum_buysell',
        'trip_forum_travelmate',
        'trip_image',
        'trip_offer'
    ];


    protected $forumTypeMap = [
        'trip_forum' => 'forum',
        'trip_forum_buysell' => 'forum',
        'trip_forum_expat' => 'forum',
        'trip_forum_other' => 'forum'
    ];

    public function __construct()
    {
        parent::__construct();

        $this->client = new \GuzzleHttp\Client();
        Model::unguard();
        
        // max size: messages ~100000

        $this->skip = env('CONVERT_SKIP', 10);
        $this->take = env('CONVERT_TAKE', 10);
        $this->copyFiles = env('CONVERT_FILES', false);
        $this->scrambleMessages = env('CONVERT_SCRAMBLE', true);
        $this->fileHash = env('CONVERT_FILEHASH', false);
        $this->overwriteFiles = env('CONVERT_OVERWRITE', false);
        $this->demoAccounts = env('CONVERT_DEMOACCOUNTS', false);
    }

    // Nodes

    public function getNode($nid)
    {
        $query = \DB::connection($this->connection)
            ->table('node')
            ->join('node_revisions', 'node_revisions.nid', '=', 'node.nid')
            ->where('node.nid', '=', $nid)
            ->where('node.uid', '>', 0)
            ->where('node.status', '=', 1)
            ->first();

        return $query;
    }

    public function getNodes($type)
    {
        $query = \DB::connection($this->connection)
            ->table('node')
            ->join('node_revisions', 'node_revisions.nid', '=', 'node.nid')
            ->select('node.*', 'node_revisions.body')
            ->where('node.uid', '>', 0)
            ->where('node.status', '=', 1)
            ->where('node.type', '=', $type)
            ->orderBy('node.last_comment', 'desc')
            ->skip($this->skip)
            ->take($this->take);
        
        return $query;
    }

    public function convertNode($node, $modelname, $type)
    {
        
        if (!$modelname::find($node->nid)) {

            if ($this->isUserConvertable($node->uid)) {

                $user_id = ($node->uid > 0) ? $node->uid : 1;

                $model = new $modelname;

                $model->id = $node->nid;
                $model->type = $type;
                $model->user_id = $user_id;
                $model->title = $this->cleanAll($node->title);
                $model->body = $this->clean($node->body);

                $model->start_at = isset($node->start_at) ? $node->start_at : null;
                $model->end_at = isset($node->end_at) ? $node->end_at : null;
                $model->duration = isset($node->duration) ? $this->cleanAll($node->duration) : null;
                $model->price = (isset($node->price) && is_int((int)$node->price)) ? $node->price : null;

                $model->status = 1;
                $model->created_at = \Carbon\Carbon::createFromTimeStamp($node->created);  
                $model->updated_at = \Carbon\Carbon::createFromTimeStamp($node->last_comment); 

                $model->save();
            
                $this->convertUser($node->uid);
                $this->convertComments($node->nid);
                $this->convertFlags($node->nid, 'App\Content', 'node');
                $this->convertAlias($node->nid, 'App\Content', 'node');

                return $model;
            
            } else {

                return false;
            
            }

        }

    }


    // Terms

    public function getTerms($vids)
    {
        return \DB::connection($this->connection)
            ->table('term_data')
            ->join('term_hierarchy', 'term_data.tid', '=', 'term_hierarchy.tid')        
            ->whereIn('term_data.vid', (array) $vids)
            ->orderBy('term_data.tid');
    }

    public function getTermById($id)
    {
        return \DB::connection($this->connection)
            ->table('term_data')  
            ->join('term_hierarchy', 'term_data.tid', '=', 'term_hierarchy.tid')        
            ->where('term_data.tid', '=', $id)
            ->first();
    }

    public function getTermByName($name)
    {
        return \DB::connection($this->connection)
            ->table('term_data')  
            ->join('term_hierarchy', 'term_data.tid', '=', 'term_hierarchy.tid')        
            ->where('term_data.name', '=', $name)
            ->first();
    }

    public function createTerm($term, $modelname, $setParent = false)
    {
        if (!$modelname::find($term->tid)) {
            
            $model = new $modelname;
            $model->id = $term->tid;
            $model->name = $this->cleanAll($term->name);

            if ($setParent)
            {
                $model->parent_id = $term->parent;
            }
            
            $model->save();
        
        }
    }

    // Topics

    public $topicMap = [
    
        'Konkurss' => ['delete' => true],
        'Trip.ee tänab' => ['move' => 'Trip.ee tagasiside'], // !
        'Up Traveli reisijutu konkurss' => ['move' => 'Trip.ee tagasiside'],
        'Luksusreis' => ['delete' => true],
        'Paadimatk' => ['move' => 'Matkamine'],
        'Jalgsimatk' => ['move' => 'Matkamine'],
        'Toidu-joogireis' => ['rename' => 'Söök ja jook'],
        'Mägimatk' => ['move' => 'Matkamine'],
        'Sukeldumine' => ['rename' => 'Sukeldumine'],
        'Reisivaluuta' => ['move' => 'Hinnad kohapeal'],
        'Reisikaardid' => ['move' => 'Reisiraamatud'],
        'Reisiveeb' => ['delete' => true],
        'Autahvel' => ['delete' => true],
        'Reisijuhid' => ['rename' => 'Reisijuhid'],
        'Trip.ee tagasiside' => ['rename' => 'Trip.ee'],
        'Reisimeditsiin' => ['rename' => 'Tervis'],
        'Jalgrattamatk' => ['move' => 'Matkamine'],
        'Reisifoto' => ['rename' => 'Fotod'],
        'Lendude soodukad' => ['move' => 'Lendamine ja lennufirmad'],
        'Kultuurireis' => ['delete' => true],
        'Laevareis' => ['rename' => 'Laevad ja kruiisid'],
        'Hinnad kohapeal' => ['rename' => 'Raha ja hinnad'],
        'Häälega reis' => ['move' => 'Seljakotireis'],
        'Inimesed' => ['rename' => 'Kohalikud inimesed'],
        'Lastega reis' => ['rename' => 'Lastega reisimine'],
        'Seljakotireis' => ['rename' => 'Seljakotireis ja hääletamine'],
        'Reisivarustus' => ['rename' => 'Varustus'],
        'Reisidokumendid' => ['rename' => 'Viisad'],
        'Reisiideed' => ['delete' => true],
        'Reisiöömaja' => ['rename' => 'Majutus ja hotellid'],
        'Reisikiri' => ['delete' => true],
        'Auto-motoreis' => ['rename' => 'Autoreis'],
//      'Reisivideo' => ['delete' => true],

        'Matkavarustus' => ['rename' => 'Varustus'],
        'Reisikirjandus' => ['rename' => 'Reisiraamatud'],
        'Reisi-öömaja' => ['rename' => 'Majutus ja hotellid'],
//      'Voucherid ja piletid' => [],

        'Lemmikloom reisil' => ['tid' => 5000, 'pattern' => '/(lemmikloom|koer|\skass)/i'],
        'GPS ja kaardid' => ['tid' => 5001, 'pattern' => '/GPS/'],
        'Autorent' => ['tid' => 5002, 'pattern' => '/(autorent|rendia|renti)/i'],
        'Motoreis' => ['tid' => 5003, 'pattern' => '/(mootor|moto)/i'],
        'Turvalisus' => ['tid' => 5004, 'pattern' => '/(turval|varasta)/i'],
        'Uuring' => ['tid' => 5005, 'pattern' => '/(uuring|uurimus|küsitlus)/i'],
        'Töö' => ['tid' => 5006, 'pattern' => '/(töö)/i']

    ];


    public function processTopic($topic)
    {
        
        if ($rename = array_get($this->topicMap, $topic->name . '.rename')) {
            
            $topic->name = $rename;
        
        }

        if (array_get($this->topicMap, $topic->name . '.move') || array_get($this->topicMap, $topic->name . '.delete')) {
        
            return false;
        
        }

        return $topic;
    }

    public function getNewTopics()
    {

        $topics = [];

        array_walk($this->topicMap, function($value, $key) use (&$topics) {
            
            if (array_key_exists('tid', $value)) {   
                
                $topics[$key] = array_merge($value, ['name' => $key]);
            
            }
        
        });

        return $topics;
    }


    // Node terms

    public function getNodeTerms($nid, $vids)
    {
        return \DB::connection($this->connection)
            ->table('term_node')
            ->join('term_data', 'term_node.tid', '=', 'term_data.tid')              
            ->where('term_node.nid', '=', $nid)
            ->whereIn('term_data.vid', (array) $vids)
            ->get();
    }

    public function insertPivot($table, $key1, $value1, $key2, $value2)
    {

        $values = [$key1 => $value1, $key2 => $value2];

        if (! DB::table($table)->where($values)->get()) {
        
            DB::table($table)->insert($values);

        }

    }

    public function processNodeTopic($topic)
    {
        if ($move = array_get($this->topicMap, $topic->name . '.move')) {
            $new = $this->getTermByName($move);
            return $new;
        }

        if (array_get($this->topicMap, $topic->name . '.delete')) {
            return false;
        }

        return $topic;
    }


    public function convertNodeDestinations($node)
    {
        $terms = $this->getNodeTerms($node->nid, 6); // Sihtkohad

        foreach ($terms as $term) {
            $this->insertPivot('content_destination', 'content_id', $node->nid, 'destination_id', $term->tid);
        }
    }

    public function convertNodeTopics($node)
    {
        
        $terms = $this->getNodeTerms($node->nid, [5, 9]); // Reisistiilid, Rubriigid

        foreach ($terms as $term) {
            if ($processed_term = $this->processNodeTopic($term)) {
                $this->insertPivot('content_topic', 'content_id', $node->nid, 'topic_id', $processed_term->tid);
            }            
        }
 
    }

    public function newNodeTopics($node)
    {
        $topics = $this->getNewTopics();

        foreach ($topics as $topic) {

            if (preg_match($topic['pattern'], $node->title . $node->body)) {
 
                $this->insertPivot('content_topic', 'content_id', $node->nid, 'topic_id', $topic['tid']);
            }

        }
 
    }

    
    public function convertNodeCarriers($node)
    {
        $terms = $this->getNodeTerms($node->nid, 23); // Lennufirmad

        foreach ($terms as $term) {
            $this->insertPivot('content_carrier', 'content_id', $node->nid, 'carrier_id', $term->tid);
        }
    }



    // Comments

    public function getComments($nid)
    {
        return \DB::connection($this->connection)
            ->table('comments')
            ->where('nid', '=', $nid);
    }

    public function convertComments($nid, $status = 0)
    {            
        $comments = $this->getComments($nid)->where('status', '=', $status)->get();

        foreach($comments as $comment) {
            
            $user_id = ($comment->uid > 0) ? $comment->uid : 1;
            
            if ($this->isUserConvertable($user_id)) {
                
                $model = new \App\Comment;

                $model->id = $comment->cid;
                $model->user_id = $user_id;
                $model->content_id = $comment->nid;
                $model->body = $this->clean($comment->comment);
                $model->status = 1 - $comment->status;
                $model->created_at = \Carbon\Carbon::createFromTimeStamp($comment->timestamp);  
                $model->updated_at = \Carbon\Carbon::createFromTimeStamp($comment->timestamp); 

                $model->save();
        
                $this->convertUser($user_id);
                
                $this->convertFlags($comment->cid, 'App\Comment', 'comment');
                
            }
        
        }
    
    }


    // Users

    protected $roleMap = [
        'authenticated user' => 'regular',
        'administrator' => 'admin',
        'editor' => 'admin',
        'senior editor' => 'admin',
        'Ärikasutaja' => 'regular',
        'Tavakasutaja' => 'regular',
        'Ärikasutaja 2' => 'regular',
        'superuser' => 'superuser',
        'viktoriin' => 'regular',
    ];

    public function getUser($uid)
    {
        return \DB::connection($this->connection)
            ->table('users')      
            ->join('users_roles', 'users_roles.uid', '=', 'users.uid')
            ->where('users.uid', '=', $uid)
            ->first();
    }

    public function getProfileFields($uid)
    {

        $profile = DB::connection($this->connection)
            ->table('profile_values')
            ->where('uid', '=', $uid)
            ->lists('value', 'fid');
        
        return $profile;
    
    }

    public function isUserConvertable($uid) {

        $user = $this->getUser($uid);

        $blockedSender = DB::connection($this->connection)
            ->table('pm_block_user')
            ->where('author', '=', $uid)
            ->get();

        // Eliminating mail duplicates using
        // SELECT uid, mail, COUNT(*) c FROM users GROUP BY mail HAVING c > 1;

        return ($user
            && $user->status == 1
            && ! $blockedSender
            && ! in_array($user->uid, [7288556, 4694, 3661])
        );
    
    }

    public function getRole($rid)
    {
        $role = \DB::connection($this->connection)
            ->table('role')      
            ->whereRid($rid)
            ->lists('name')[0];
        
        return $this->roleMap[$role];
    }

    public function getUserNotifyMessage($uid) {

        return \DB::connection($this->connection)
            ->table('pm_email_notify')      
            ->where('user_id', $uid)
            ->first();    
    }

    public function createUser($user)
    {

        if ($this->isUserConvertable($user->uid)) {

            $model = new \App\User;

            $profile = $this->getProfileFields($user->uid);

            $model->id = $user->uid;
            $model->name = $this->cleanAll($user->name);
            $model->email = $this->cleanAll($user->mail);

            if (isset($profile) && isset($profile[13])) {

                $homepage = $this->cleanUrl($profile[13]);

                $model->contact_homepage = (
                    $this->isUrl($homepage)
                    && ! $this->isFacebookUrl($homepage)
                    && ! $this->isTwitterUrl($homepage)
                    && ! $this->isInstagramUrl($homepage)
                    )
                    ? $homepage
                    : null;

                $model->contact_facebook = ($this->isUrl($homepage)
                    && $this->isFacebookUrl($homepage))
                    ? $homepage
                    : null;

                $model->contact_twitter = ($this->isUrl($homepage)
                    && $this->isTwitterUrl($homepage))
                    ? $homepage
                    : null;

                $model->contact_instagram = ($this->isUrl($homepage)
                    && $this->isInstagramUrl($homepage))
                    ? $homepage
                    : null;


            }

            // $model->password = bcrypt($this->cleanAll($user->name)); 

            $model->password = $user->pass;

            $model->role = $this->getRole($user->rid);

            $model->created_at = \Carbon\Carbon::createFromTimeStamp($user->created);  
            $model->updated_at = \Carbon\Carbon::createFromTimeStamp($user->created);  
           
            $model->save();

            $model->verified = true;
            $model->registration_token = null;

            // if ($notifyMessage = $this->getUserNotifyMessage($user->uid)) {
            //
            //    $model->notify_message = 1; 
            //
            // }

            $model->save();

            if ($user->picture) {

                $this->convertLocalImage($user->uid, $user->picture, '\App\User', 'user');
            
            }

        }
    
    }


    public function convertUser($uid)
    {
        if (!\App\User::find($uid) && $uid > 0) {

            $user = $this->getUser($uid);
            $this->createUser($user);
            
            $this->convertUserDestinationFlags($uid);
        }
        
    }

    // Fields

    public function convertUrl($id, $url, $modelName)
    {

        $model = $modelName::findOrFail($id);

        $model->url = $this->cleanUrl($url);

        $model->save();
    
    }

    public function convertLocalImage($id, $imagePath, $modelName, $type)
    {

        $imagePath = $this->cleanAll($imagePath);
        $filename = basename($imagePath);

        $model = $modelName::findOrFail($id);

        if (method_exists($model, 'images')) {

            $image = \App\Image::create(['filename' => $filename]);        
            $model->images()->attach($image);

        } else {

            $model->image = $filename;
            $model->save();
        
        }

        $from = 'http://trip.ee/' . $imagePath;
        $to = public_path() . '/images/original/' . $filename;

        if ($this->copyFiles) {

            if (file_exists($to) && !$this->overwriteFiles) {

                return false;
            
            }

            $this->copyFile($from, $to);

            if ($type == 'user') {

                $this->createUserThumbnail($from, $to);

            } else {

                $this->createThumbnail($from, $to);

            }

        }
    
    }

    public function convertRemoteImage($id, $imageUrl, $modelName, $type)
    {

        $newImage = false;

        $imageUrl = $this->cleanAll($imageUrl);

        if (array_key_exists('filename', pathinfo($imageUrl)) && array_key_exists('extension', pathinfo($imageUrl))) {

            $file = pathinfo($imageUrl)['filename'];
            $ext = pathinfo($imageUrl)['extension'];

            if ($this->fileHash) {
                
                $filename = $file . '-' . strtolower(str_random(4)) . '.' . $ext;
            
            } else {

                $filename = $file . '.' . $ext;
                
            }

            $filename = str_replace('%20', '-', $filename);

            $model = $modelName::findOrFail($id);
            
            if (method_exists($model, 'images')) {

                $image = \App\Image::create(['filename' => $filename]);        
                $model->images()->attach($image);

                $newImage = $image;

            } else {

                $model->image = $filename;
                $model->save();
            
            }

            $from = $imageUrl;
            $to = public_path() . '/images/original/' . $filename;

            if ($this->copyFiles) {
            
                if (file_exists($to) && !$this->overwriteFiles) {

                    return $newImage;
                
                }

                $this->copyFile($from, $to);
                $this->createThumbnail($from, $to);
            
            }

        }
    
        return $newImage;

    }


    // Flags

    public function getFlags($id, $type)
    {
        return \DB::connection($this->connection)
           ->table('flag_content')
           ->where('content_id', $id)
           ->where('content_type', $type) // node, comment, term
           ->orderBy('timestamp', 'desc')
           ->get();

    }


    public function getUserDestinationFlags($uid)
    {
        return \DB::connection($this->connection)
           ->table('flag_content')
           ->where('uid', $uid)
           ->where('content_type', 'term')
           ->whereIn('fid', [6, 7])
           ->orderBy('timestamp', 'desc')
           ->get();

    }


    public function createFlag($flag, $modelname)
    {

        $user_id = ($flag->uid > 0) ? $flag->uid : 1;

        if ($user_id == 1) {

            $user_id = 12;
        
        }

        $model = new \App\Flag;

        $model->user_id = $user_id;

        $model->flag_type = $flag->flag_type;

        $model->flaggable_type = $modelname;
        $model->flaggable_id = $flag->content_id;

        $model->created_at = \Carbon\Carbon::createFromTimeStamp($flag->timestamp);  
        $model->updated_at = \Carbon\Carbon::createFromTimeStamp($flag->timestamp);  
       
        $model->save();

    }

    public function convertFlags($id, $modelname, $type)
    {
        $flag_map = array(
            '2' => 'good',
            '3' => 'bad',
            '4' => 'good',
            '5' => 'bad'
         );

        $flags = $this->getFlags($id, $type);
            
        foreach($flags as $flag) {

            if ($this->isUserConvertable($flag->uid) && array_key_exists($flag->fid, $flag_map)) {

                $flag->flag_type = $flag_map[$flag->fid];
                $this->createFlag($flag, $modelname);

                $this->convertUser($flag->uid);

            }
        }   
    }

    public function convertUserDestinationFlags($uid)
    {

        $flag_map = array(
            '6' => 'havebeen',
            '7' => 'wantstogo',
         );

        $flags = $this->getUserDestinationFlags($uid);
        
        if ($flags) {

            foreach($flags as $flag) {

                if (array_key_exists($flag->fid, $flag_map)) {

                    $flag->flag_type = $flag_map[$flag->fid];
                    $this->createFlag($flag, 'App\Destination');

                }
            }

        }
    
    }

    // Aliases

    public function getAlias($nid)
    {
        return \DB::connection($this->connection)
            ->table('url_alias')
            ->where('src', '=', 'node/' . $nid)
            ->first();
    }

    public function convertAlias($nid)
    {
        if ($alias = $this->getAlias($nid)) {

        \DB::table('content_alias')
            ->insert([
                'content_id' => $nid,
                'alias' => $this->cleanAll($alias->dst)
            ]);
        }
    
    }

    // Utils 
    
    public function copyFile($from, $to)
    {
        try {

            $response = $this->client->get($from, [
                'save_to' => $to,
                'exceptions' => false
            ]);
        
        }

        catch(\GuzzleHttp\Exception\ConnectException $e) {

            return false;
        }

        catch(\GuzzleHttp\Exception\RequestException $e) {

            return false;
        }


        return true;
    }

 public function createUserThumbnail($from, $to)
    {

        try {

            Imageconv::make($to)
                ->fit(80)
                ->save(dirname($to) . '/../xsmall_square/' . basename($to), $this->imageQuality);
 
            Imageconv::make($to)
                ->fit(180)
                ->save(dirname($to) . '/../small_square/' . basename($to), $this->imageQuality);
        
        }

        catch (\Intervention\Image\Exception\NotReadableException $e) {} 
        catch (\Intervention\Image\Exception\NotSupportedException $e) {} 
        catch (\Symfony\Component\Debug\Exception\FatalErrorException $e) {}
    }

    public function createThumbnail($from, $to)
    {

        try {

            Imageconv::make($to)
                ->resize(300, null, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->save(dirname($to) . '/../small/' . basename($to), $this->imageQuality);

            Imageconv::make($to)
                ->resize(700, null, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->save(dirname($to) . '/../medium/' . basename($to), $this->imageQuality);

            Imageconv::make($to)
                ->resize(900, null, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->save(dirname($to) . '/../large/' . basename($to), $this->imageQuality);

        }

        catch (\Intervention\Image\Exception\NotReadableException $e) {} 
        catch (\Intervention\Image\Exception\NotSupportedException $e) {} 
        catch (\Symfony\Component\Debug\Exception\FatalErrorException $e) {}
    }


    public function chunkLimit()
    {

        return ($this->take / $this->chunk) - 1;
    }

    public function formatTimestamp($timestamp)
    {

        if (! $timestamp) return null;

        return \Carbon\Carbon::createFromTimeStamp($timestamp)->toDateTimeString();
    }

    public function formatDateTime($datetime)
    {
        if (! $datetime) return null;

        $el = explode('T', $datetime);

        return \Carbon\Carbon::createFromFormat('Y-m-d', $el[0])->startOfDay()->toDateTimeString();
    }

    public function formatFields($node, $fields)
    {
    
        return  join("\n", array_map(function($field) use ($node) {
        
            return '<strong>' . $field . '</strong>: ' . $node->$field;
    
        }, $fields));
    
    }

    public function scrambleString($string)
    {

        $string = strip_tags($string);
        $output = '';

        for ($i = 0; $i < strlen($string) - 1; $i++) {
            
            $char = mb_substr($string, $i, 1);
            $output .= preg_match("/[A-Ya-y]/", $char) ? chr(ord($char) + 1) : $char;
        
        }

        return $output;

    }

    public function clean($string)
    {

        $string = strip_tags($string, config('site.allowedtags'));
        $string = trim($string);
        $string = $this->removeComments($string);
        $string = $this->removeUppercase($string);
        $string = $this->removeReferrals($string);
        $string = $this->convertUnderlineHeaders($string);
        $string = $this->convertStrongHeaders($string);
        $string = $this->convertTexyUrls($string);
        $string = $this->convertUmlauts($string);
        $string = $this->convertLineendings($string);

        return $string;

    }

    public function cleanAll($string)
    {

        $string = strip_tags($this->clean($string));

        return $string;

    }

    public function cleanUrl($string)
    {

        return $this->cleanAll(mb_convert_case($string, MB_CASE_LOWER, 'UTF-8'));

    }

    public function removeComments($string)
    {

        return preg_replace('/<!--(.*?)-->/', '', $string);

    }

    public function convertUnderlineHeaders($string)
    {

        return preg_replace("/\n<u>(.*)<\/u>/", "\n<h4>$1</h4>", $string);

    }

    public function convertStrongHeaders($string)
    {

        return preg_replace("/\n<strong>(.*)<\/strong>/", "\n<h4>$1</h4>", $string);

    }

    public function convertLineendings($string)
    {

        $string = preg_replace("/^\n/", "", $string);
        $string = preg_replace("/\r\n/", "\n", $string);
        $string = preg_replace("/\n{3,}/", "\n\n", $string);

        return $string;

    }

    public function convertTexyUrls($string)
    {

        return preg_replace("/\"(.*)\":(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\\\".,<>?«»“”‘’%]))/i", "<a href=\"$2\">$1</a>", $string);
        return preg_replace("/\"(.*)\":(?:https?:\/\/.*)\s/i", "<a href=\"$2\">$1</a>", $string);
   
    }

    public function convertUmlauts($string)
    {

        return str_replace('ó', 'õ', $string);

    }

    public function removeReferrals($string)
    {

        return preg_replace("/<i>(.*)<\/i>\s*!\s*$/i", '', $string);   
    
    }

    public function removeUppercase($string)
    {

        if ($string == mb_convert_case($string, MB_CASE_UPPER, 'UTF-8')) {

            return mb_convert_case(mb_substr($string, 0, 1), MB_CASE_UPPER, 'UTF-8')
                . mb_convert_case(mb_substr($string, 1), MB_CASE_LOWER, 'UTF-8');
        }

        return $string;

    }

    public function isUrl($url)
    {

        return filter_var($url, FILTER_VALIDATE_URL);
    
    }

    public function isFacebookUrl($url)
    {

        return preg_match("/(.*)\.facebook\.com(.*)/", $url);
    
    }

    public function isInstagramUrl($url)
    {

        return preg_match("/(.*)\.instagram\.com(.*)/", $url);
    
    }

    public function isTwitterUrl($url)
    {

        return preg_match("/(.*)\.twitter\.com(.*)/", $url);
    
    }

}
