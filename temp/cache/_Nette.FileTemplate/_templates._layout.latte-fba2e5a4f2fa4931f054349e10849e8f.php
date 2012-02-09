<?php //netteCache[01]000386a:2:{s:4:"time";s:21:"0.32087800 1328775278";s:9:"callbacks";a:2:{i:0;a:3:{i:0;a:2:{i:0;s:19:"Nette\Caching\Cache";i:1;s:9:"checkFile";}i:1;s:64:"C:\Users\pH\web\test8\app\DatabaseModule\templates\@layout.latte";i:2;i:1328775275;}i:1;a:3:{i:0;a:2:{i:0;s:19:"Nette\Caching\Cache";i:1;s:10:"checkConst";}i:1;s:25:"Nette\Framework::REVISION";i:2;s:30:"013c8ee released on 2012-02-03";}}}?><?php

// source file: C:\Users\pH\web\test8\app\DatabaseModule\templates\@layout.latte

?><?php
// prolog Nette\Latte\Macros\CoreMacros
list($_l, $_g) = Nette\Latte\Macros\CoreMacros::initRuntime($template, 'lgsj6ip083')
;
// prolog Nette\Latte\Macros\UIMacros

// snippets support
if (!empty($_control->snippetMode)) {
	return Nette\Latte\Macros\UIMacros::renderSnippets($_control, $_l, get_defined_vars());
}

//
// main template
//
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<title>Migrace DB</title>
		<meta name="description" content="" />
		<meta name="author" content="" />

    <!-- Le styles -->
		<link href="/css/bootstrap.min.css" rel="stylesheet" />
		<style type="text/css">
			body {
				padding-top: 60px;
				padding-bottom: 40px;
			}
		</style>
	</head>

	<body>

		<div class="container">

      <!-- Example row of columns -->
			<div class="row">
				<div class="span12">
<?php Nette\Latte\Macros\UIMacros::callBlock($_l, 'content', $template->getParameters()) ?>
				</div>
			</div>

			<hr />

			<footer>
				
			</footer>

    </div> <!-- /container -->


	</body>
</html>
