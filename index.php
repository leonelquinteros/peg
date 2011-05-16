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
		
		foreach($solutions as $i => $s)
		{
			echo "<h1>Solution $i</h1>";
			
			$s->renderPlay = true;
			$s->saveGame();
			$s->playMoves();
			
			echo '<div style="clear:both;border-top:1px solid #444;">';
		}
		
		// Example of first game solution fetched from the DB.
		echo "<h1>Last solution fetched from DB</h1>";
		$s->playLastGame();
		?>
	</body>
</html>
