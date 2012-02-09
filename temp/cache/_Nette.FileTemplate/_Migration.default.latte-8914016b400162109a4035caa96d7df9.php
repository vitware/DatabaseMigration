<?php //netteCache[01]000396a:2:{s:4:"time";s:21:"0.80146500 1328775582";s:9:"callbacks";a:2:{i:0;a:3:{i:0;a:2:{i:0;s:19:"Nette\Caching\Cache";i:1;s:9:"checkFile";}i:1;s:74:"C:\Users\pH\web\test8\app\DatabaseModule\templates\Migration\default.latte";i:2;i:1328775493;}i:1;a:3:{i:0;a:2:{i:0;s:19:"Nette\Caching\Cache";i:1;s:10:"checkConst";}i:1;s:25:"Nette\Framework::REVISION";i:2;s:30:"013c8ee released on 2012-02-03";}}}?><?php

// source file: C:\Users\pH\web\test8\app\DatabaseModule\templates\Migration\default.latte

?><?php
// prolog Nette\Latte\Macros\CoreMacros
list($_l, $_g) = Nette\Latte\Macros\CoreMacros::initRuntime($template, 'c49e8wa66w')
;
// prolog Nette\Latte\Macros\UIMacros
//
// block content
//
if (!function_exists($_l->blocks['content'][] = '_lb2228cbf214_content')) { function _lb2228cbf214_content($_l, $_args) { extract($_args)
?>
<div class="hero-unit">
	<h1>Migrace DB</h1>
	<p>Přenášejte strukuru <strong>Vaší databáze</strong> snadno a s lehkostí.</p>
	<h2>Hlavní rysy:</h2>
	<ul>
		<li>Ukládá struktru do člověkem čitelného formátu (Neon)</li>
		<li>Porovná uloženou strukturu s strukturou používané databáze</li>
		<li>Navrhne SQL dotaz k změnám</li>
		<li>Dokáže rozpoznat přejmenování tabulky i sloupce</li>
		
	</ul>

	<h2>Omezení:</h2>
	<ul>
		<li>Žádná tabulka se nesmí jmenovat "__table"</li>
	
	</ul>
</div>
<h1>Struktura databáze</h1>
<p>Výpis všech tabulek v db, včetně sloupců a cizích klíčů</p>

<?php $iterations = 0; foreach ($tables as $table_name => $table): ?>
<h2><?php echo Nette\Templating\Helpers::escapeHtml($table_name, ENT_NOQUOTES) ?></h2>
<table class="table table-striped">
<?php $iterations = 0; foreach ($table as $column): Nette\Diagnostics\Debugger::barDump(array('$column' => $column), "Template " . str_replace(dirname(dirname($template->getFile())), "\xE2\x80\xA6", $template->getFile())) ?>
	<tr>
		<th><?php echo Nette\Templating\Helpers::escapeHtml($column['Field'], ENT_NOQUOTES) ?></th>
		<td>
			<span title="<?php echo htmlSpecialChars($column['Type']) ?>"><?php echo Nette\Templating\Helpers::escapeHtml($template->truncate($column['Type'], 30), ENT_NOQUOTES) ?></span>
		</td>
		<td><?php echo Nette\Templating\Helpers::escapeHtml($column['Key'], ENT_NOQUOTES) ?></td>
		<td>


<?php if ($column['Null'] == 'YES'): ?>
			<em>NULL</em>
<?php endif ?>
				<?php echo Nette\Templating\Helpers::escapeHtml($column['Default'], ENT_NOQUOTES) ?>

				<?php echo Nette\Templating\Helpers::escapeHtml($column['Extra'], ENT_NOQUOTES) ?>

<?php if ($column['Reference'] != NULL): ?>
					<?php echo Nette\Templating\Helpers::escapeHtml($column['Reference'][0], ENT_NOQUOTES) ?>
.<?php echo Nette\Templating\Helpers::escapeHtml($column['Reference'][1], ENT_NOQUOTES) ?>

<?php endif ?>


		</td>
		<td>
	</tr>
<?php $iterations++; endforeach ?>
</table>
<?php $iterations++; endforeach ;
}}

//
// end of blocks
//

// template extending and snippets support

$_l->extends = empty($template->_extended) && isset($_control) && $_control instanceof Nette\Application\UI\Presenter ? $_control->findLayoutTemplateFile() : NULL; $template->_extended = $_extended = TRUE;


if ($_l->extends) {
	ob_start();

} elseif (!empty($_control->snippetMode)) {
	return Nette\Latte\Macros\UIMacros::renderSnippets($_control, $_l, get_defined_vars());
}

//
// main template
//
?>

<?php if ($_l->extends) { ob_end_clean(); return Nette\Latte\Macros\CoreMacros::includeTemplate($_l->extends, get_defined_vars(), $template)->render(); }
call_user_func(reset($_l->blocks['content']), $_l, get_defined_vars()) ; 