<div class="hero"><div><p class="eyebrow">TÁMOGATÁS</p><h1>Hibabejelentések</h1><p><?=is_supervisor()?'Az összes bejelentés kezelése.':'Saját bejelentések és válaszok.'?> Email-értesítést nem küldünk.</p></div></div>
<details class="panel add" <?=!$ticket?'open':''?>><summary>+ Új bejelentés</summary>
<form method="post" action="?page=tickets" class="stack">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<label>Email-cím<input name="email" type="email" required maxlength="254" autocomplete="email"></label>
<label>Típus<select name="category" required><option value="">Válassz típust</option><?php foreach(ticket_categories() as $value=>$label): ?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach?></select></label>
<label>Tárgy<input name="subject" required maxlength="160"></label>
<label>Leírás<textarea name="body" required maxlength="10000" rows="5"></textarea></label>
<button name="action" value="create_ticket">Bejelentés elküldése</button></form></details>
<?php if($ticket): ?>
<section class="panel"><div class="panel-title"><h2>#<?=$ticket['id']?> · <?=e($ticket['subject'])?></h2><span><?=$ticket['status']==='open'?'Aktív':'Lezárt'?></span></div>
<p><?=e(ticket_categories()[$ticket['category']])?> · <?=e($ticket['username'])?> · <?=e($ticket['email'])?> · <?=e($ticket['created_at'])?></p>
<div class="message"><?=nl2br(e($ticket['body']))?></div>
<?php foreach($comments as $comment): ?><article class="message"><p><b><?=e($comment['username'])?></b> · <?=e($comment['created_at'])?></p><?=nl2br(e($comment['body']))?></article><?php endforeach?>
<?php if($ticket['status']==='open'): ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="ticket_id" value="<?=$ticket['id']?>"><label>Hozzászólás<textarea name="body" required maxlength="10000" rows="4"></textarea></label><button name="action" value="comment_ticket">Hozzászólás elküldése</button></form><?php else: ?><p>A bejelentés lezárult.</p><?php endif?>
<?php if(is_supervisor()): ?><form method="post" class="status-form"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="ticket_id" value="<?=$ticket['id']?>"><input type="hidden" name="status" value="<?=$ticket['status']==='open'?'closed':'open'?>"><button name="action" value="ticket_status"><?=$ticket['status']==='open'?'Lezárás':'Újranyitás'?></button></form><?php endif?>
</section><?php endif?>
<section class="panel"><h2><?=is_supervisor()?'Beérkezett bejelentések':'Saját bejelentések'?></h2>
<?php if(!$tickets): ?><p>Még nincs bejelentés.</p><?php endif?>
<div class="ticket-list"><?php foreach($tickets as $row): ?><a href="?page=tickets&amp;ticket=<?=$row['id']?>"><b>#<?=$row['id']?> · <?=e($row['subject'])?></b><span><?=e(ticket_categories()[$row['category']])?> · <?=$row['status']==='open'?'Aktív':'Lezárt'?> · <?=e($row['username'])?></span></a><?php endforeach?></div></section>
