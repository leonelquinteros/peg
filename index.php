<?php
require('peg.php');

$p = new PegPlayer();
?>

<!DOCTYPE html>

<html>
	<head>
		<title>Peg solitaire</title>
		<link rel="stylesheet" type="text/css" href="styles.css" />
	</head>
	
	<body>
		<?php
		$solutions = $p->play();
		
		print_r($solutions);
		
		/*
		foreach($solutions as $s)
		{
			$s->renderPlay = true;
			$s->saveGame();
			$s->playMoves();
			
			echo '<div style="clear:both;border-top:1px solid #444;">';
		}
		*/
		?>
	</body>
</html>
