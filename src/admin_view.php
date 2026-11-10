<div class="hero"><div><p class="eyebrow">ADMINISZTRÁCIÓ</p><h1>Csapat és jogosultságok</h1><p>Felhasználók és raktár-hozzáférések kezelése.</p></div></div>
<section class="panel"><h2>Új felhasználó</h2>
<form method="post" class="grid-form user-form">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<label>Felhasználónév<input name="username" required maxlength="50" autocomplete="off"></label>
<label>Ideiglenes jelszó<input name="password" type="password" required minlength="10" autocomplete="new-password"></label>
<label>Szerepkör<select name="role"><?php foreach(role_labels() as $value=>$label): if(!is_admin() && $value>1) continue; ?><option value="<?=$value?>"><?=e($label)?></option><?php endforeach?></select></label>
<button name="action" value="create_user">Felvétel</button></form></section>
<?php foreach($users as $u): $own=(int)$u['id']===(int)user()['id']; $manageable=!$own && (is_admin() || (int)$u['role']<=ROLE_EDITOR); ?>
<section class="panel"><div class="panel-title"><h2><?=e($u['username'])?></h2><span><?=e(role_labels()[(int)$u['role']])?><?=$own?' · saját fiók':''?></span></div>
<?php if(is_admin() && !$own): ?><form method="post" class="inline-form">
<input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="user_id" value="<?=$u['id']?>">
<label>Szerepkör<select name="role"><?php foreach(role_labels() as $value=>$label): ?><option value="<?=$value?>" <?=$value===(int)$u['role']?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label><button name="action" value="save_role">Szerepkör mentése</button></form><?php endif?>
<?php if((int)$u['role']===ROLE_ADMIN): ?><p>Minden raktárhoz hozzáfér.</p>
<?php elseif($manageable): ?>
<div class="access-list"><?php foreach($inventories as $inv): ?><div><span><b><?=e($inv['name'])?></b><small><?=e($inv['location'])?></small></span>
<form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="user_id" value="<?=$u['id']?>"><input type="hidden" name="inventory_id" value="<?=$inv['id']?>">
<label class="switch"><input type="checkbox" name="allowed" <?=isset($access[(int)$u['id']][(int)$inv['id']])?'checked':''?>> Hozzáférés</label><button name="action" value="save_access">Mentés</button></form></div><?php endforeach?></div>
<?php if(!$inventories): ?><p>Nincs kezelhető raktár.</p><?php endif?>
<?php else: ?><p>Ennek a fióknak a hozzáférését itt nem módosíthatod.</p><?php endif?>
</section><?php endforeach?>
