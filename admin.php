<?php
require_once __DIR__.'/api.php';
sess();
function cut($s,$n){return function_exists('mb_substr')?mb_substr((string)$s,0,$n):substr((string)$s,0,$n);}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
if(ADMIN_HASH==='')exit('Set ADMIN_HASH in api.php first (see the comment next to it).');
$_SESSION['csrf']??=bin2hex(random_bytes(16));
$msg='';$P=$_POST;$isAdmin=!empty($_SESSION['admin']);
$https=fn($v)=>preg_match('#^https://\S+$#i',trim((string)$v))?trim($v):null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($P['do']??'')==='login'){
    if(password_verify($P['password']??'',ADMIN_HASH)){session_regenerate_id(true);$_SESSION['admin']=1;$_SESSION['csrf']=bin2hex(random_bytes(16));header('Location: admin.php');exit;}
    sleep(1);$msg='Wrong password.';
  }elseif($isAdmin&&hash_equals($_SESSION['csrf'],$P['csrf']??'')){
    $do=$P['do']??'';$id=(int)($P['id']??0);
    if($do==='logout'){session_destroy();header('Location: admin.php');exit;}
    if($do==='toggle')db()->prepare("UPDATE artists SET status=IF(status='draft','published','draft') WHERE id=?")->execute([$id]);
    if($do==='delete')db()->prepare("DELETE FROM artists WHERE id=?")->execute([$id]);
    if($do==='hero_add'&&!empty($_FILES['hero']['tmp_name'][0])){
      @mkdir(__DIR__.'/uploads/hero',0755,true);$n=0;
      foreach($_FILES['hero']['tmp_name'] as $k=>$tmp){
        if($_FILES['hero']['error'][$k]!==0)continue;
        if(count(heroes())>=5){$msg='You can keep up to 5 background photos. Remove one first.';break;}
        $i=@getimagesize($tmp);$ext=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'][$i[2]??0]??null;
        if(!$ext||$_FILES['hero']['size'][$k]>5242880){$msg='A file was skipped: use JPG, PNG or WebP under 5 MB.';continue;}
        move_uploaded_file($tmp,__DIR__.'/uploads/hero/'.bin2hex(random_bytes(8)).'.'.$ext);$n++;
      }
      if($n&&!$msg)$msg=$n.' background photo(s) added.';
    }
    if($do==='hero_del'){$f=basename($P['file']??'');if(preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/',$f))@unlink(__DIR__.'/uploads/hero/'.$f);$msg='Background photo removed.';}
    if($do==='save'){
      $name=trim($P['stage_name']??'');$cid=(int)($P['category_id']??0);
      $slug=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name)),'-')?:'artist';
      if($name===''||!$cid)$msg='Stage name and category are required.';
      else{
        $d=db()->prepare("SELECT id FROM artists WHERE slug=? AND id<>?");$d->execute([$slug,$id]);
        if($d->fetch())$msg='That stage name is already taken.';
      }
      $photo=null;
      if(!$msg&&!empty($_FILES['photo']['tmp_name'])&&$_FILES['photo']['error']===0){
        $i=@getimagesize($_FILES['photo']['tmp_name']);
        $ext=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'][$i[2]??0]??null;
        if(!$ext||$_FILES['photo']['size']>5242880)$msg='Photo must be a JPG, PNG or WebP under 5 MB.';
        else{@mkdir(__DIR__.'/uploads/artists',0755,true);$photo='uploads/artists/'.bin2hex(random_bytes(8)).'.'.$ext;move_uploaded_file($_FILES['photo']['tmp_name'],__DIR__.'/'.$photo);}
      }
      if(!$msg){
        $f=[$name,$slug,$cid,cut($P['bio']??'',1500),cut($P['curator_note']??'',200),$https($P['yt_url']??''),$https($P['ig_url']??''),$https($P['tiktok_url']??''),$https($P['x_url']??''),$https($P['fb_url']??''),($P['status']??'')==='published'?'published':'draft'];
        if($id)db()->prepare("UPDATE artists SET stage_name=?,slug=?,category_id=?,bio=?,curator_note=?,yt_url=?,ig_url=?,tiktok_url=?,x_url=?,fb_url=?,status=?,photo_url=COALESCE(?,photo_url) WHERE id=?")->execute([...$f,$photo,$id]);
        else db()->prepare("INSERT INTO artists(stage_name,slug,category_id,bio,curator_note,yt_url,ig_url,tiktok_url,x_url,fb_url,status,photo_url)VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")->execute([...$f,$photo]);
        header('Location: admin.php?saved=1');exit;
      }
    }
  }
}
if(!empty($_GET['saved']))$msg='Saved.';
$cats=$isAdmin?db()->query("SELECT id,name FROM categories ORDER BY id")->fetchAll():[];
$edit=[];
if($isAdmin&&!empty($_GET['edit'])){$s=db()->prepare("SELECT * FROM artists WHERE id=?");$s->execute([(int)$_GET['edit']]);$edit=$s->fetch()?:[];}
$roster=$isAdmin?db()->query("SELECT a.id,a.stage_name,a.status,c.name cat,COUNT(r.id) n FROM artists a JOIN categories c ON c.id=a.category_id LEFT JOIN ratings r ON r.artist_id=a.id GROUP BY a.id ORDER BY a.created_at DESC")->fetchAll():[];
$v=fn($k)=>h($edit[$k]??'');
$heroes=$isAdmin?heroes():[];
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<title>TAJI Curator</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}
body{margin:0;background:#0A0A0A;color:#F5EFE6;font:16px/1.6 system-ui,sans-serif;padding:16px;max-width:760px;margin:auto}
h1,h2{margin:.6em 0 .3em}a{color:#FF6B6B}
label{display:block;margin:12px 0 4px;color:#B8AFA3;font-size:14px}
input,select,textarea{width:100%;min-height:46px;padding:10px 12px;border-radius:10px;border:1px solid #3a2a2a;background:#151111;color:#F5EFE6;font:inherit}
button{min-height:44px;padding:0 18px;border:0;border-radius:999px;background:#C81E2B;color:#fff;font:inherit;font-weight:700;cursor:pointer}
button.g{background:#211818;border:1px solid #3a2a2a}
.r{display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;background:#151111;border:1px solid #2a1c1c;border-radius:12px;padding:10px 14px;margin-bottom:8px}
.r form{display:inline}.m{background:#211818;border-left:4px solid #C81E2B;padding:10px 14px;border-radius:8px;margin:10px 0}
:focus-visible{outline:2px solid #FF6B6B;outline-offset:2px}
</style></head><body>
<h1>TAJI Curator</h1>
<?php if($msg)echo '<div class="m" role="status">'.h($msg).'</div>';
if(!$isAdmin){ ?>
<form method="post"><input type="hidden" name="do" value="login"><label for="pw">Password</label><input id="pw" type="password" name="password" required autofocus><p><button>Sign in</button></p></form>
<?php }else{ $csrf=h($_SESSION['csrf']); ?>
<form method="post" style="float:right"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="do" value="logout"><button class="g">Sign out</button></form>
<p><a href="index.html">View site</a></p>
<h2>Home page background photos</h2>
<p style="color:#B8AFA3;margin:0 0 8px">Shown behind the headline on the first page, fading one after another. Use landscape photos (about 1600×900, under 300 KB is ideal). Up to 5.</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="do" value="hero_add">
<input type="file" name="hero[]" accept="image/jpeg,image/png,image/webp" multiple required aria-label="Choose background photos"><p><button>Upload background photos</button></p></form>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<?php foreach($heroes as $hp)echo '<form method="post" style="text-align:center"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="do" value="hero_del"><input type="hidden" name="file" value="'.h(basename($hp)).'"><img src="'.h($hp).'" alt="Background photo" style="width:150px;height:84px;object-fit:cover;border-radius:8px;display:block;margin-bottom:6px"><button class="g">Remove</button></form>';
if(!$heroes)echo '<p style="color:#B8AFA3">No background photos yet.</p>';?>
</div>
<h2><?=$edit?'Edit artist':'Add an artist'?></h2>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>">
<label for="n">Stage name</label><input id="n" name="stage_name" maxlength="80" required value="<?=$v('stage_name')?>">
<label for="c">Category</label><select id="c" name="category_id"><?php foreach($cats as $c)echo '<option value="'.$c['id'].'"'.(($edit['category_id']??0)==$c['id']?' selected':'').'>'.h($c['name']).'</option>';?></select>
<label for="b">Story (max 1,500 characters)</label><textarea id="b" name="bio" rows="5" maxlength="1500"><?=$v('bio')?></textarea>
<label for="p">Photo (JPG, PNG or WebP, max 5 MB)</label><input id="p" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
<label for="y">Featured YouTube link</label><input id="y" name="yt_url" type="url" placeholder="https://www.youtube.com/watch?v=…" value="<?=$v('yt_url')?>">
<label for="i">Instagram</label><input id="i" name="ig_url" type="url" value="<?=$v('ig_url')?>">
<label for="t">TikTok</label><input id="t" name="tiktok_url" type="url" value="<?=$v('tiktok_url')?>">
<label for="x">X</label><input id="x" name="x_url" type="url" value="<?=$v('x_url')?>">
<label for="f">Facebook</label><input id="f" name="fb_url" type="url" value="<?=$v('fb_url')?>">
<label for="o">Why they’re here (max 200)</label><input id="o" name="curator_note" maxlength="200" value="<?=$v('curator_note')?>">
<label for="s">Status</label><select id="s" name="status"><option value="published"<?=($edit['status']??'')==='published'?' selected':''?>>Published</option><option value="draft"<?=($edit['status']??'draft')==='draft'?' selected':''?>>Draft</option></select>
<p><button>Save artist</button> <?php if($edit)echo '<a href="admin.php">Cancel</a>';?></p>
</form>
<h2>Roster</h2>
<?php if(!$roster)echo '<p>No artists yet. Add the first one above.</p>';
foreach($roster as $a){ ?>
<div class="r"><span><b><?=h($a['stage_name'])?></b> <small style="color:#B8AFA3"><?=h($a['cat'])?> · <?=h($a['status'])?> · <?=(int)$a['n']?> votes</small></span><span>
<a href="admin.php?edit=<?=(int)$a['id']?>">Edit</a>
<form method="post"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button class="g"><?=$a['status']==='draft'?'Publish':'Unpublish'?></button></form>
<form method="post" onsubmit="return confirm('Delete this artist and all their votes?')"><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button class="g">Delete</button></form></span></div>
<?php }} ?>
</body></html>
