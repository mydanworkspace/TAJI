<?php
/* TAJI API. Edit the CONFIG block, upload to your host, done. */
const DB_HOST='localhost', DB_NAME='taji', DB_USER='root', DB_PASS='';
const ADMIN_HASH='$2y$10$O1ItVWczh137KjAZjCLGZetLfUbQ/Gd.pC.b1Mk1oO8UIeOs6k9zC';     // run: php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
const DEV_OTP=false;     // true shows the login code on screen (testing only, never in production)
const MAIL_FROM='no-reply@yourdomain.com';
const MIN_VOTES=10;      // fairness threshold for the weighted ranking

function db(){static $p;return $p??=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
function sess(){if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);session_start();}}
function heroes(){$f=glob(__DIR__.'/uploads/hero/*.{jpg,png,webp}',GLOB_BRACE)?:[];usort($f,fn($a,$b)=>filemtime($a)<=>filemtime($b));return array_map(fn($x)=>'uploads/hero/'.basename($x),array_slice($f,0,5));}
function out($d,$c=200){http_response_code($c);header('Content-Type: application/json');echo json_encode($d);exit;}

/* Published artists with average, vote count, weighted score and rank. */
function board(){
  $rows=db()->query("SELECT a.id,a.slug,a.stage_name,a.category_id,a.bio,a.photo_url,a.curator_note,a.yt_url,a.ig_url,a.tiktok_url,a.x_url,a.fb_url,COUNT(r.id) n,COALESCE(AVG(r.score),0) avg FROM artists a LEFT JOIN ratings r ON r.artist_id=a.id WHERE a.status='published' GROUP BY a.id")->fetchAll();
  $tv=array_sum(array_column($rows,'n'));
  $C=$tv?array_sum(array_map(fn($x)=>$x['avg']*$x['n'],$rows))/$tv:7;
  foreach($rows as &$x){
    $x['id']=(int)$x['id'];$x['category_id']=(int)$x['category_id'];$x['n']=(int)$x['n'];$x['avg']=round((float)$x['avg'],2);
    $x['w']=round($x['n']?($x['n']/($x['n']+MIN_VOTES))*$x['avg']+(MIN_VOTES/($x['n']+MIN_VOTES))*$C:0,2);
  }unset($x);
  usort($rows,fn($a,$b)=>$b['w']<=>$a['w']);
  foreach($rows as $i=>&$x)$x['rank']=$i+1;
  return $rows;
}

if(realpath(__FILE__)===realpath($_SERVER['SCRIPT_FILENAME'])){
  sess();$a=$_GET['a']??'';$in=json_decode(file_get_contents('php://input'),true)?:[];
  try{
    if($_SERVER['REQUEST_METHOD']==='POST'&&($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='taji')out(['error'=>'Bad request.'],400);
    switch($a){
      case 'home':
        out(['cats'=>db()->query("SELECT id,name,slug,description FROM categories ORDER BY id")->fetchAll(),'list'=>board(),'hero'=>heroes()]);
      case 'health':
        $c=['php_version'=>PHP_VERSION,'database'=>false,'tables'=>false,'uploads_writable'=>is_writable(__DIR__.'/uploads'),'admin_password_set'=>ADMIN_HASH!==''];
        try{db();$c['database']=true;$c['tables']=(int)db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn()>=6;}
        catch(Throwable $ex){if(DEV_OTP)$c['database_error']=$ex->getMessage();}
        out($c);
      case 'me':
        out(['user'=>isset($_SESSION['uid'])?['email'=>$_SESSION['email']]:null]);
      case 'artist':
        $art=null;foreach(board() as $x)if($x['slug']===($_GET['slug']??''))$art=$x;
        if(!$art)out(['error'=>'Not found.'],404);
        $d=array_fill(0,10,0);
        $s=db()->prepare("SELECT score,COUNT(*) c FROM ratings WHERE artist_id=? GROUP BY score");$s->execute([$art['id']]);
        foreach($s->fetchAll() as $r)$d[$r['score']-1]=(int)$r['c'];
        $mine=null;
        if(!empty($_SESSION['uid'])){$m=db()->prepare("SELECT score FROM ratings WHERE user_id=? AND artist_id=?");$m->execute([$_SESSION['uid'],$art['id']]);$v=$m->fetchColumn();$mine=$v?(int)$v:null;}
        out(['artist'=>$art,'dist'=>$d,'mine'=>$mine]);
      case 'otp_request':
        $e=strtolower(trim($in['email']??''));
        if(!filter_var($e,FILTER_VALIDATE_EMAIL))out(['error'=>'Enter a valid email.'],422);
        $c=db()->prepare("SELECT COUNT(*) FROM otp_codes WHERE email=? AND created_at>NOW()-INTERVAL 10 MINUTE");$c->execute([$e]);
        if($c->fetchColumn()>=5)out(['error'=>'Too many codes requested. Wait a few minutes.'],429);
        $code=(string)random_int(100000,999999);
        db()->prepare("INSERT INTO otp_codes(email,code_hash,expires_at)VALUES(?,?,NOW()+INTERVAL 10 MINUTE)")->execute([$e,password_hash($code,PASSWORD_DEFAULT)]);
        @mail($e,'Your TAJI code',"Your TAJI code is $code. It expires in 10 minutes.",'From: '.MAIL_FROM);
        out(['ok'=>1]+(DEV_OTP?['dev_code'=>$code]:[]));
      case 'otp_verify':
        $e=strtolower(trim($in['email']??''));$code=trim($in['code']??'');
        $s=db()->prepare("SELECT * FROM otp_codes WHERE email=? AND used=0 AND attempts<5 AND expires_at>NOW() ORDER BY id DESC LIMIT 1");$s->execute([$e]);$r=$s->fetch();
        if(!$r)out(['error'=>'Code expired. Request a new one.'],422);
        db()->prepare("UPDATE otp_codes SET attempts=attempts+1 WHERE id=?")->execute([$r['id']]);
        if(!password_verify($code,$r['code_hash']))out(['error'=>'That code is not right.'],422);
        db()->prepare("UPDATE otp_codes SET used=1 WHERE id=?")->execute([$r['id']]);
        db()->prepare("INSERT IGNORE INTO users(email)VALUES(?)")->execute([$e]);
        $u=db()->prepare("SELECT id FROM users WHERE email=?");$u->execute([$e]);
        session_regenerate_id(true);$_SESSION['uid']=(int)$u->fetchColumn();$_SESSION['email']=$e;
        out(['ok'=>1,'user'=>['email'=>$e]]);
      case 'logout':
        session_destroy();out(['ok'=>1]);
      case 'vote':
        if(empty($_SESSION['uid']))out(['error'=>'Sign in to vote.'],401);
        $aid=(int)($in['artist_id']??0);$sc=(int)($in['score']??0);
        if($sc<1||$sc>10)out(['error'=>'Score must be 1 to 10.'],422);
        $ip=$_SERVER['REMOTE_ADDR']??'';
        $c=db()->prepare("SELECT COUNT(*) FROM vote_log WHERE ip=? AND created_at>NOW()-INTERVAL 10 MINUTE");$c->execute([$ip]);
        if($c->fetchColumn()>=30)out(['error'=>'Too many votes from this network. Try again soon.'],429);
        $p=db()->prepare("SELECT id FROM artists WHERE id=? AND status='published'");$p->execute([$aid]);
        if(!$p->fetch())out(['error'=>'Artist not found.'],404);
        try{db()->prepare("INSERT INTO ratings(user_id,artist_id,score)VALUES(?,?,?)")->execute([$_SESSION['uid'],$aid,$sc]);}
        catch(PDOException $ex){if($ex->getCode()==='23000')out(['error'=>'You already voted for this artist.'],409);throw $ex;}
        db()->prepare("INSERT INTO vote_log(ip)VALUES(?)")->execute([$ip]);
        out(['ok'=>1]);
      default:
        out(['error'=>'Unknown action.'],404);
    }
  }catch(Throwable $ex){out(['error'=>'Server error. Check the database settings in api.php.'],500);}
}
