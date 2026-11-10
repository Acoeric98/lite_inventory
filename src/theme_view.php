<details class="theme-settings"><summary>Megjelenés</summary>
<form method="post" class="theme-panel stack" id="theme-form">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<label>Megjelenítési mód<select name="theme_mode" id="theme-mode">
<?php foreach(['light'=>'Világos', 'dark'=>'Sötét', 'system'=>'Rendszer', 'custom'=>'Egyéni (custom)'] as $value=>$label): ?><option value="<?=$value?>" <?=$themeMode===$value?'selected':''?>><?=e($label)?></option><?php endforeach?>
</select></label>
<fieldset id="custom-colors"><legend>Egyéni színek</legend>
<?php foreach(['background'=>'Háttér', 'surface'=>'Panelek', 'text'=>'Szöveg', 'muted'=>'Másodlagos szöveg', 'accent'=>'Kiemelés', 'border'=>'Szegélyek'] as $key=>$label): ?>
<label><?=e($label)?><input type="color" name="colors[<?=$key?>]" data-color="<?=$key?>" value="<?=e($themeColors[$key])?>"></label>
<?php endforeach?></fieldset>
<p>A módosítás előnézet. A Mentés a saját fiókodhoz rögzíti; a korábbi egyéni színeid módváltáskor megmaradnak.</p>
<button name="action" value="save_theme">Mentés</button>
<button type="button" id="theme-cancel">Előnézet visszaállítása</button>
</form></details>
