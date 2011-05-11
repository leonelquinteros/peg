<?php
require('peg.php');

$peg = new Peg();
$peg->renderPlay = true;
$peg->playToWin = true;
?>

<!DOCTYPE html>

<html>
	<head>
		<title>Peg solitaire</title>
		<link rel="stylesheet" type="text/css" href="styles.css" />
	</head>
	
	<body>
		<?php 
		$peg->play();
		
		echo '<div style="clear:both;border-top:1px solid #444;">';
		
		$peg->saveGame();
		$peg->playLastGame(); 
		?>
	</body>
</html>
