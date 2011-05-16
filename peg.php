<?php
/**
 * Class to manage DB connection and game persistence.
 */
class PegDB
{
	/**
	 * MySQL connection parameters.
	 */
	private $dbHost 	= 'localhost';
	private $dbUser 	= 'root';
	private $dbPassword = 'Mko0@wsx';
	private $dbName		= 'peg';
	
	public function __construct()
	{
		mysql_connect($this->dbHost, $this->dbUser, $this->dbPassword);
		
		if(!mysql_select_db($this->dbName))
		{
			mysql_query('CREATE DATABASE ' . $this->dbName);
			mysql_select_db($this->dbName);
		}
	}
	
	/**
	 * Inserts play moves into DB.
	 *
	 * @param array $moves
	 */
	public function saveGame($moves)
	{
		$this->setupDB();
		
		mysql_query('INSERT INTO games SET date = NOW()');
		$gameId = mysql_insert_id();
		
		foreach($moves as $i => $move)
		{
			$query = 'INSERT INTO moves SET
						game_id = ' . $gameId . ',
						move_nr = ' . $i . ',
						from_x = ' . $move[0][1] . ',
						from_y = ' . $move[0][0] . ',
						to_x = ' . $move[1][1] . ',
						to_y = ' . $move[1][0] . '
			';
			
			mysql_query($query);
		}
	}
	
	
	/**
	 * Retrieves last game played from DB and return array of moves.
	 *
	 * @return array
	 */
	public function loadLastGame()
	{
		$rGame = mysql_query('SELECT * FROM games ORDER BY id DESC LIMIT 1');
		$game = mysql_fetch_object($rGame);
		
		$rMoves = mysql_query('SELECT * FROM moves WHERE game_id = ' . $game->id . ' ORDER BY move_nr');
		
		$moves = array();
		while($move = mysql_fetch_object($rMoves))
		{
			$moves[] = array(
						array($move->from_y, $move->from_x),
						array($move->to_y, $move->to_x)
			);
		}
		
		return $moves;
	}
	
	
	/**
	 * Creates tables if not exists.
	 */
	private function setupDB()
	{
		$query = 'CREATE TABLE IF NOT EXISTS games (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					date DATETIME
		)';
		mysql_query($query);
		
		$query = 'CREATE TABLE IF NOT EXISTS moves (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					game_id INT NOT NULL,
					move_nr INT NOT NULL,
					from_x INT NOT NULL,
					from_y INT NOT NULL,
					to_x INT NOT NULL,
					to_y INT NOT NULL,
		 			INDEX (game_id),
		 			INDEX (move_nr)
		)';
		mysql_query($query);
	}
}


/**
 * Hole class
 *
 * Represents a hole position in the board. It can have a marble or not indicated by the property 'hasMarble'.
 * Implements only the render() method to return a representation for the position.
 */
class Hole
{
	public $hasMarble;
	
	public function __construct($marble = false)
	{
		$this->hasMarble = $marble;
	}
	
	public function render()
	{
		if($this->hasMarble)
		{
			return '<td class="hole">X</td>';
		}
		else
		{
			return '<td class="hole">&nbsp;</td>';
		}
	}
}


/**
 * Peg class
 *
 * Represents a board of a Peg solitaire game.
 *
 */
class Peg
{
	/**
	 * Fixed array with move directions available.
	 *
	 * @var array
	 */
	private $moveDestinations;
	
	/**
	 * Board representation.
	 *
	 * @var array
	 */
	private $board;
	
	/**
	 * Log of moves performed.
	 *
	 * @var array
	 */
	public $moves;
	
	/**
	 * Indicates if the game should render each move result.
	 * @var boolean
	 */
	public $renderPlay;
	
	
	public function __construct()
	{
		$this->moveDestinations = array('up', 'right', 'down', 'left');
		$this->resetBoard();
		$this->resetMoves();
	}
	
	
	function __clone()
    {
		$this->board = unserialize(serialize($this->board));
    }
	
	
	/**
	 * Initializes the board array.
	 */
	public function resetBoard()
	{
		$this->board = array();
		
		// Not available positions in the square.
		$this->board[3][-3] = false;
		$this->board[3][-2] = false;
		$this->board[2][-3] = false;
		$this->board[2][-2] = false;
		
		$this->board[-2][-3] = false;
		$this->board[-2][-2] = false;
		$this->board[-3][-3] = false;
		$this->board[-3][-2] = false;
		
		$this->board[3][2] = false;
		$this->board[3][3] = false;
		$this->board[2][2] = false;
		$this->board[2][3] = false;
		
		$this->board[-2][2] = false;
		$this->board[-2][3] = false;
		$this->board[-3][2] = false;
		$this->board[-3][3] = false;
		
		// Fill the available positions.
		for($i = 3; $i >= -3; $i--)
		{
			for($j = -3; $j <= 3; $j++)
			{
				if(!isset($this->board[$i][$j]))
				{
					if($i == 0 && $j == 0) // Center position empty
					{
						$this->board[$i][$j] = new Hole();
					}
					else
					{
						$this->board[$i][$j] = new Hole(true);
					}
				}
			}
		}
	}
	
	
	public function resetMoves()
	{
		$this->moves = array();
	}
	
	
	/**
	 * Renders the board in HTML.
	 */
	public function renderBoard()
	{
		$render = '<div class="boardContainer"><div class="board"><table>';
		
		for($i = 3; $i >= -3; $i--)
		{
			$render .= '<tr>';
			for($j = -3; $j <= 3; $j++)
			{
				if( $this->board[$i][$j] )
				{
					$render .= $this->board[$i][$j]->render();
				}
				else
				{
					$render .= '<td>&nbsp;</td>';
				}
			}
			$render .= '</tr>';
		}
		
		$render .= '</table></div></div>';
		
		return $render;
	}
	
	
	/**
	 * Saves the game using PegDB class.
	 */
	public function saveGame()
	{
		$db = new PegDB();
		
		return $db->saveGame($this->moves);
	}
	
	
	/**
	 * Retrieves last game played from PegDB class and plays it.
	 */
	public function playLastGame()
	{
		$this->resetBoard();
		
		if($this->renderPlay)
		{
			echo $this->renderBoard();
		}
		
		$db = new PegDB();
		
		$moves = $db->loadLastGame();
		
		foreach($moves as $move)
		{
			$this->move($move[0], $move[1]);
		}
	}
	
	
	/**
	 * Plays the game using the moves stored.
	 */
	public function playMoves()
	{
		$this->resetBoard();
		$moves = unserialize(serialize($this->moves));
		$this->resetMoves();
		
		foreach($moves as $move)
		{
			$this->move($move[0], $move[1]);
		}
	}
	
	
	/**
	 * Find paths to win.
	 */
	public function findPath()
	{
		$pegs = array();
		$moves = array();
		
		$aux = $this->findRectangleMove();
		foreach($aux as $move)
		{
			$moves[] = $move;
		}
		
		$aux = $this->findBigLMove();
		foreach($aux as $move)
		{
			$moves[] = $move;
		}
		
		$aux = $this->findLMove();
		foreach($aux as $move)
		{
			$moves[] = $move;
		}
		
		if(empty($moves))
		{
			if(count($this->moves) > 28)
			{
				$moves = $this->findSimpleMove();
			}
		}
		
		foreach($moves as $move)
		{
			$newPeg = clone $this;
			
			foreach($move as $m)
			{
				$newPeg->moveTo($m[0], $m[1], $m[2]);
			}
			
			$pegs[] = clone $newPeg;
		}
		
		return $pegs;
	}
	
	
	/**
	 * Performs a marble move.
	 *
	 * @param $from 	Initial position coordinates.
	 * @param $to   	Destination coordinates
	 */
	private function move($from, $to)
	{
		$marble = & $this->board[$from[0]][$from[1]];
		$dest = & $this->board[$to[0]][$to[1]];
		
		$jump0 = $from[0];
		$jump1 = $from[1];
		
		if($from[0] < $to[0])
		{
			$jump0++;
		}
		elseif($from[0] > $to[0])
		{
			$jump0--;
		}
		elseif($from[1] < $to[1])
		{
			$jump1++;
		}
		elseif($from[1] > $to[1])
		{
			$jump1--;
		}
		
		$jump = & $this->board[$jump0][$jump1];
		
		try
		{
			if($marble->hasMarble && $jump->hasMarble && !$dest->hasMarble)
			{
				$marble->hasMarble = false;
				$jump->hasMarble = false;
				$dest->hasMarble = true;
			}
			else
			{
				return false;
			}
			
			if($this->renderPlay)
			{
				echo $this->renderBoard();
			}
			
			$this->moves[] = array($from, $to);
			
			return true;
		}
		catch(Exception $e)  // Not a valid move
		{
			return false;
		}
	}
	
	
	/**
	 * Checks if a marble can move in some direction.
	 *
	 * @param $direction	One of the available directions (up, right, down, left)
	 * @param $i			Origin's Y coordinate
	 * @param $j			Origin's X coordinate
	 */
	private function canMoveTo($direction, $i, $j)
	{
		try
		{
			$hole = & $this->board[$i][$j];
			
			switch($direction)
			{
				case 'up':
					$jump = & $this->board[$i + 1][$j];
					$dest = & $this->board[$i + 2][$j];
					$return = array($i + 2, $j);
					break;
				
				case 'right':
					$jump = & $this->board[$i][$j + 1];
					$dest = & $this->board[$i][$j + 2];
					$return = array($i, $j + 2);
					break;
					
				case 'down':
					$jump = & $this->board[$i - 1][$j];
					$dest = & $this->board[$i - 2][$j];
					$return = array($i - 2, $j);
					break;
					
				case 'left':
					$jump = & $this->board[$i][$j - 1];
					$dest = & $this->board[$i][$j - 2];
					$return = array($i, $j - 2);
					break;
			}
		}
		catch(Exception $e) // Some board position doesn't exists
		{
			return false;
		}
		
		if(	$hole && $hole->hasMarble )
		{
			if(	(!empty($jump) && $jump->hasMarble) && (!empty($dest) && !$dest->hasMarble) )
			{
				return $return;
			}
		}
		
		return false;
	}
	
	
	/**
	 * Performs a move in some direction
	 *
	 * @param $direction	One of the available directions (up, right, down, left)
	 * @param $i			Origin's Y coordinate
	 * @param $j			Origin's X coordinate
	 */
	private function moveTo($direction, $i, $j)
	{
		if( $dest = $this->canMoveTo($direction, $i, $j) )
		{
			$this->move(array($i, $j), $dest);
		}
		else
		{
			return false;
		}
	}
	
	
	/**
	 * Find moves available for Big L blocks.
	 *
	 * Representation of a Big L block:
	 *
	 *     X
	 *   X X O
	 *     X
	 *     X X X
	 *
	 *
	 * @return array 	Possible moves.
	 */
	private function findBigLMove()
	{
		$moves = array();
		
		// Top
		try
		{
			if(
				$this->board[3][-1]->hasMarble &&
				$this->board[3][0]->hasMarble &&
				$this->board[3][1]->hasMarble
			)
			{
				// Left
				if(	$this->board[2][-1]->hasMarble &&
					$this->board[1][-1]->hasMarble &&
					$this->board[0][-1]->hasMarble &&
					(
						($this->board[1][-2]->hasMarble && !$this->board[1][0]->hasMarble) ||
						(!$this->board[1][-2]->hasMarble && $this->board[1][0]->hasMarble)
					)
				)
				{
					$moves[0] = array();
					
					if($this->board[1][-2]->hasMarble && !$this->board[1][0]->hasMarble)
					{
						$moves[0][] = array('right', 1, -2);
					}
					else
					{
						$moves[0][] = array('left', 1, 0);
					}
					
					$moves[0][] = array('down', 3, -1);
					$moves[0][] = array('left', 3, 1);
					$moves[0][] = array('up', 0, -1);
					$moves[0][] = array('down', 3, -1);
					
					if($this->board[1][-2]->hasMarble && !$this->board[1][0]->hasMarble)
					{
						$moves[0][] = array('right', 1, -2);
					}
					else
					{
						$moves[0][] = array('left', 1, 0);
					}
				}
				
				// Right
				if(	$this->board[2][1]->hasMarble &&
					$this->board[1][1]->hasMarble &&
					$this->board[0][1]->hasMarble &&
					(
						($this->board[1][2]->hasMarble && !$this->board[1][0]->hasMarble) ||
						(!$this->board[1][2]->hasMarble && $this->board[1][0]->hasMarble)
					)
				)
				{
					$moves[1] = array();
					
					if($this->board[1][2]->hasMarble && !$this->board[1][0]->hasMarble)
					{
						$moves[1][] = array('left', 1, 2);
					}
					else
					{
						$moves[1][] = array('right', 1, 0);
					}
					
					$moves[1][] = array('down', 3, 1);
					$moves[1][] = array('right', 3, -1);
					$moves[1][] = array('up', 0, 1);
					$moves[1][] = array('down', 3, 1);
					
					if($this->board[1][2]->hasMarble && !$this->board[1][0]->hasMarble)
					{
						$moves[1][] = array('left', 1, 2);
					}
					else
					{
						$moves[1][] = array('right', 1, 0);
					}
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Down
		try
		{
			if(
				$this->board[-3][-1]->hasMarble &&
				$this->board[-3][0]->hasMarble &&
				$this->board[-3][1]->hasMarble
			)
			{
				// Left
				if(	$this->board[-2][-1]->hasMarble &&
					$this->board[-1][-1]->hasMarble &&
					$this->board[0][-1]->hasMarble &&
					(
						($this->board[-1][-2]->hasMarble && !$this->board[-1][0]->hasMarble) ||
						(!$this->board[-1][-2]->hasMarble && $this->board[-1][0]->hasMarble)
					)
				)
				{
					$moves[2] = array();
					
					if($this->board[-1][-2]->hasMarble && !$this->board[-1][0]->hasMarble)
					{
						$moves[2][] = array('right', -1, -2);
					}
					else
					{
						$moves[2][] = array('left', -1, 0);
					}
					
					$moves[2][] = array('up', -3, -1);
					$moves[2][] = array('left', -3, 1);
					$moves[2][] = array('down', 0, -1);
					$moves[2][] = array('up', -3, -1);
					
					if($this->board[-1][-2]->hasMarble && !$this->board[-1][0]->hasMarble)
					{
						$moves[2][] = array('right', -1, -2);
					}
					else
					{
						$moves[2][] = array('left', -1, 0);
					}
				}
				
				// Right
				if(	$this->board[-2][1]->hasMarble &&
					$this->board[-1][1]->hasMarble &&
					$this->board[0][1]->hasMarble &&
					(
						($this->board[-1][2]->hasMarble && !$this->board[-1][0]->hasMarble) ||
						(!$this->board[-1][2]->hasMarble && $this->board[-1][0]->hasMarble)
					)
				)
				{
					$moves[3] = array();
					
					if($this->board[-1][2]->hasMarble && !$this->board[-1][0]->hasMarble)
					{
						$moves[3][] = array('left', -1, 2);
					}
					else
					{
						$moves[3][] = array('right', -1, 0);
					}
					
					$moves[3][] = array('up', -3, 1);
					$moves[3][] = array('right', -3, -1);
					$moves[3][] = array('down', 0, 1);
					$moves[3][] = array('up', -3, 1);
					
					if($this->board[-1][2]->hasMarble && !$this->board[-1][0]->hasMarble)
					{
						$moves[3][] = array('left', -1, 2);
					}
					else
					{
						$moves[3][] = array('right', -1, 0);
					}
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Left
		try
		{
			if(
				$this->board[1][-3]->hasMarble &&
				$this->board[0][-3]->hasMarble &&
				$this->board[-1][-3]->hasMarble
			)
			{
				// Up
				if(	$this->board[1][-2]->hasMarble &&
					$this->board[1][-1]->hasMarble &&
					$this->board[1][0]->hasMarble &&
					(
						($this->board[2][-1]->hasMarble && !$this->board[0][-1]->hasMarble) ||
						(!$this->board[2][-1]->hasMarble && $this->board[0][-1]->hasMarble)
					)
				)
				{
					$moves[4] = array();
					
					if($this->board[2][-1]->hasMarble && !$this->board[0][-1]->hasMarble)
					{
						$moves[4][] = array('down', 2, -1);
					}
					else
					{
						$moves[4][] = array('up', 0, -1);
					}
					
					$moves[4][] = array('right', 1, -3);
					$moves[4][] = array('up', -1, -3);
					$moves[4][] = array('left', 1, 0);
					$moves[4][] = array('right', 1, -3);
					
					if($this->board[2][-1]->hasMarble && !$this->board[0][-1]->hasMarble)
					{
						$moves[4][] = array('down', 2, -1);
					}
					else
					{
						$moves[4][] = array('up', 0, -1);
					}
				}
				
				// Down
				if(	$this->board[-1][-2]->hasMarble &&
					$this->board[-1][-1]->hasMarble &&
					$this->board[-1][0]->hasMarble &&
					(
						($this->board[-2][-1]->hasMarble && !$this->board[0][-1]->hasMarble) ||
						(!$this->board[-2][-1]->hasMarble && $this->board[0][-1]->hasMarble)
					)
				)
				{
					$moves[5] = array();
					
					if($this->board[-2][-1]->hasMarble && !$this->board[0][-1]->hasMarble)
					{
						$moves[5][] = array('up', -2, -1);
					}
					else
					{
						$moves[5][] = array('down', 0, -1);
					}
					
					$moves[5][] = array('right', -1, -3);
					$moves[5][] = array('down', 1, -3);
					$moves[5][] = array('left', 1, 0);
					$moves[5][] = array('right', -1, -3);
					
					if($this->board[-2][-1]->hasMarble && !$this->board[0][-1]->hasMarble)
					{
						$moves[5][] = array('up', 2, -1);
					}
					else
					{
						$moves[5][] = array('down', 0, -1);
					}
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Right
		try
		{
			if(
				$this->board[1][3]->hasMarble &&
				$this->board[0][3]->hasMarble &&
				$this->board[-1][3]->hasMarble
			)
			{
				// Up
				if(	$this->board[1][2]->hasMarble &&
					$this->board[1][1]->hasMarble &&
					$this->board[1][0]->hasMarble &&
					(
						($this->board[2][1]->hasMarble && !$this->board[0][1]->hasMarble) ||
						(!$this->board[2][1]->hasMarble && $this->board[0][1]->hasMarble)
					)
				)
				{
					$moves[6] = array();
					
					if($this->board[2][1]->hasMarble && !$this->board[0][1]->hasMarble)
					{
						$moves[6][] = array('down', 2, 1);
					}
					else
					{
						$moves[6][] = array('up', 0, 1);
					}
					
					$moves[6][] = array('left', 1, 3);
					$moves[6][] = array('up', -1, 3);
					$moves[6][] = array('right', 1, 0);
					$moves[6][] = array('left', 1, 3);
					
					if($this->board[2][1]->hasMarble && !$this->board[0][1]->hasMarble)
					{
						$moves[6][] = array('down', 2, 1);
					}
					else
					{
						$moves[6][] = array('up', 0, 1);
					}
				}
				
				// Down
				if(	$this->board[-1][2]->hasMarble &&
					$this->board[-1][1]->hasMarble &&
					$this->board[-1][0]->hasMarble &&
					(
						($this->board[-2][1]->hasMarble && !$this->board[0][1]->hasMarble) ||
						(!$this->board[-2][1]->hasMarble && $this->board[0][1]->hasMarble)
					)
				)
				{
					$moves[7] = array();
					
					if($this->board[-2][1]->hasMarble && !$this->board[0][1]->hasMarble)
					{
						$moves[7][] = array('up', -2, 1);
					}
					else
					{
						$moves[7][] = array('down', 0, 1);
					}
					
					$moves[7][] = array('left', -1, 3);
					$moves[7][] = array('down', 1, 3);
					$moves[7][] = array('right', -1, 0);
					$moves[7][] = array('left', -1, 3);
					
					if($this->board[-2][1]->hasMarble && !$this->board[0][1]->hasMarble)
					{
						$moves[7][] = array('up', -2, 1);
					}
					else
					{
						$moves[7][] = array('down', 0, 1);
					}
				}
			}
		}
		catch(Exception $e) {};
		
		
		return $moves;
	}
	
	
	/**
	 * Find available moves for L block.
	 *
	 * Representation of L block:
	 *
	 *         O
	 *     X X X
	 *         X
	 *
	 * @return array 	Possible moves.
	 */
	private function findLMove()
	{
		$moves = array();
		
		for($i = 3; $i >= -3; $i--)
		{
			for($j = -3; $j <= 3; $j++)
			{
				$hole = & $this->board[$i][$j];
				
				if($hole && !$hole->hasMarble)
				{
					try
					{
						// Right Down
						if(
							@$this->board[$i][$j + 1]->hasMarble &&
							@$this->board[$i][$j + 2]->hasMarble &&
							@$this->board[$i - 1][$j + 1]->hasMarble &&
							@$this->board[$i - 2][$j + 1]->hasMarble
						)
						{
							$moves[0] = array();
							
							$moves[0][] = array('left', $i, $j + 2);
							$moves[0][] = array('up', $i - 2, $j + 1);
							$moves[0][] = array('right', $i, $j);
						}
					}
					catch(Exception $e) {} // Not available
					
					try
					{
						// Top Left
						if(
							@$this->board[$i + 1][$j]->hasMarble &&
							@$this->board[$i + 2][$j]->hasMarble &&
							@$this->board[$i + 1][$j - 1]->hasMarble &&
							@$this->board[$i + 1][$j - 2]->hasMarble
						)
						{
							$moves[1] = array();
							
							$moves[1][] = array('down', $i + 2, $j);
							$moves[1][] = array('right', $i + 1, $j - 2);
							$moves[1][] = array('up', $i, $j);
						}
					}
					catch(Exception $e) {} // Not available
					
					
					try
					{
						// Down Right
						if(
							@$this->board[$i - 1][$j]->hasMarble &&
							@$this->board[$i - 2][$j]->hasMarble &&
							@$this->board[$i - 1][$j + 1]->hasMarble &&
							@$this->board[$i - 1][$j + 2]->hasMarble
						)
						{
							$moves[2] = array();
							
							$moves[2][] = array('up', $i - 2, $j);
							$moves[2][] = array('left', $i - 1, $j + 2);
							$moves[2][] = array('down', $i, $j);
						}
					}
					catch(Exception $e) {} // Not available
					
					try
					{
						// Down Left
						if(
							@$this->board[$i - 1][$j]->hasMarble &&
							@$this->board[$i - 2][$j]->hasMarble &&
							@$this->board[$i - 1][$j - 1]->hasMarble &&
							@$this->board[$i - 1][$j - 2]->hasMarble
						)
						{
							$moves[3] = array();
							
							$moves[3][] = array('up', $i - 2, $j);
							$moves[3][] = array('right', $i - 1, $j - 2);
							$moves[3][] = array('down', $i, $j);
						}
					}
					catch(Exception $e) {} // Not available
					
					try
					{
						// Top Right
						if(
							@$this->board[$i + 1][$j]->hasMarble &&
							@$this->board[$i + 2][$j]->hasMarble &&
							@$this->board[$i + 1][$j + 1]->hasMarble &&
							@$this->board[$i + 1][$j + 2]->hasMarble
						)
						{
							$moves[4] = array();
							
							$moves[4][] = array('down', $i + 2, $j);
							$moves[4][] = array('left', $i + 1, $j + 2);
							$moves[4][] = array('up', $i, $j);
						}
					}
					catch(Exception $e) {} // Not available
					
					try
					{
						// Right Top
						if(
							@$this->board[$i][$j + 1]->hasMarble &&
							@$this->board[$i][$j + 2]->hasMarble &&
							@$this->board[$i + 1][$j + 1]->hasMarble &&
							@$this->board[$i + 2][$j + 1]->hasMarble
						)
						{
							$moves[5] = array();
							
							$moves[5][] = array('left', $i, $j + 2);
							$moves[5][] = array('down', $i + 2, $j + 1);
							$moves[5][] = array('right', $i, $j);
						}
					}
					catch(Exception $e) {}// Not available
					
					try
					{
						// Left Top
						if(
							@$this->board[$i][$j - 1]->hasMarble &&
							@$this->board[$i][$j - 2]->hasMarble &&
							@$this->board[$i + 1][$j - 1]->hasMarble &&
							@$this->board[$i + 2][$j - 1]->hasMarble
						)
						{
							$moves[6] = array();
							
							$moves[6][] = array('right', $i, $j - 2);
							$moves[6][] = array('down', $i + 2, $j - 1);
							$moves[6][] = array('left', $i, $j);
						}
					}
					catch(Exception $e) {}// Not available
					
					try
					{
						// Left Down
						if(
							@$this->board[$i][$j - 1]->hasMarble &&
							@$this->board[$i][$j - 2]->hasMarble &&
							@$this->board[$i - 1][$j - 1]->hasMarble &&
							@$this->board[$i - 2][$j - 1]->hasMarble
						)
						{
							$moves[7] = array();
							
							$moves[7][] = array('right', $i, $j - 2);
							$moves[7][] = array('up', $i - 2, $j - 1);
							$moves[7][] = array('left', $i, $j);
						}
					}
					catch(Exception $e) {}// Not available
				}
			}
		}
		
		return $moves;
	}
	
	
	/**
	 * Find available moves for a rectangle block.
	 *
	 * Representation of a rectangle block:
	 *
	 *   X O O O
	 *     X X X
	 *     X X X
	 *
	 * @return array 	Possible moves.
	 */
	private function findRectangleMove()
	{
		$moves = array();
		
		// Top Area
		try
		{
			if(
				$this->board[3][-1]->hasMarble &&
				$this->board[3][0]->hasMarble &&
				$this->board[3][1]->hasMarble &&
				$this->board[2][-1]->hasMarble &&
				$this->board[2][0]->hasMarble &&
				$this->board[2][1]->hasMarble
			)
			{
				if(
					!$this->board[1][0]->hasMarble && !$this->board[1][-1]->hasMarble &&
					($this->board[1][1]->hasMarble && !$this->board[0][1]->hasMarble) ||
					(!$this->board[1][1]->hasMarble && $this->board[0][1]->hasMarble)
				)
				{
					$moves[0] = array();
					
					$moves[0][] = array('down', 3, -1);
					$moves[0][] = array('down', 3, 0);
					
					if(!$this->board[1][1]->hasMarble)
					{
						$moves[0][] = array('down', 3, 1);
						$moves[0][] = array('up', 0, 1);
						$moves[0][] = array('right', 1, -1);
						$moves[0][] = array('down', 2, 1);
					}
					else
					{
						$moves[0][] = array('down', 2, 1);
						$moves[0][] = array('right', 1, -1);
						$moves[0][] = array('up', 0, 1);
						$moves[0][] = array('down', 3, 1);
					}
				}
				
				if(
					!$this->board[1][0]->hasMarble && !$this->board[1][1]->hasMarble &&
					($this->board[1][-1]->hasMarble && !$this->board[0][-1]->hasMarble) ||
					(!$this->board[1][-1]->hasMarble && $this->board[0][-1]->hasMarble)
				)
				{
					$moves[1] = array();
					
					$moves[1][] = array('down', 3, 1);
					$moves[1][] = array('down', 3, 0);
					
					if(!$this->board[1][-1]->hasMarble)
					{
						$moves[1][] = array('down', 3, -1);
						$moves[1][] = array('up', 0, -1);
						$moves[1][] = array('left', 1, 1);
						$moves[1][] = array('down', 2, -1);
					}
					else
					{
						$moves[1][] = array('down', 2, -1);
						$moves[1][] = array('left', 1, 1);
						$moves[1][] = array('up', 0, -1);
						$moves[1][] = array('down', 3, -1);
					}
				}
				
				
				if($this->board[1][0]->hasMarble && !$this->board[1][-1]->hasMarble && !$this->board[1][-2]->hasMarble)
				{
					$moves[2] = array();
					
					$moves[2][] = array('down', 3, -1);
					$moves[2][] = array('left', 1, 0);
					$moves[2][] = array('left', 3, 1);
					$moves[2][] = array('left', 2, 1);
					$moves[2][] = array('down', 3, -1);
					$moves[2][] = array('right', 1, -2);
				}
				
				if(!$this->board[1][0]->hasMarble && !$this->board[1][-1]->hasMarble && $this->board[1][-2]->hasMarble)
				{
					$moves[3] = array();
					
					$moves[3][] = array('down', 3, -1);
					$moves[3][] = array('right', 1, -2);
					$moves[3][] = array('left', 3, 1);
					$moves[3][] = array('left', 2, 1);
					$moves[3][] = array('down', 3, -1);
					$moves[3][] = array('left', 1, 0);
				}
				
				if($this->board[1][0]->hasMarble && !$this->board[1][1]->hasMarble && !$this->board[1][2]->hasMarble)
				{
					$moves[4] = array();
					
					$moves[4][] = array('down', 3, 1);
					$moves[4][] = array('right', 1, 0);
					$moves[4][] = array('right', 3, -1);
					$moves[4][] = array('right', 2, -1);
					$moves[4][] = array('down', 3, 1);
					$moves[4][] = array('left', 1, 2);
				}
				
				if(!$this->board[1][0]->hasMarble && !$this->board[1][1]->hasMarble && $this->board[1][2]->hasMarble)
				{
					$moves[5] = array();
					
					$moves[5][] = array('down', 3, 1);
					$moves[5][] = array('left', 1, 2);
					$moves[5][] = array('right', 3, -1);
					$moves[5][] = array('right', 2, -1);
					$moves[5][] = array('down', 3, 1);
					$moves[5][] = array('right', 1, 0);
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Down Area
		try
		{
			if(
				$this->board[-3][-1]->hasMarble &&
				$this->board[-3][0]->hasMarble &&
				$this->board[-3][1]->hasMarble &&
				$this->board[-2][-1]->hasMarble &&
				$this->board[-2][0]->hasMarble &&
				$this->board[-2][1]->hasMarble
			)
			{
				if(
					!$this->board[-1][0]->hasMarble && !$this->board[-1][-1]->hasMarble &&
					($this->board[-1][1]->hasMarble && !$this->board[0][1]->hasMarble) ||
					(!$this->board[-1][1]->hasMarble && $this->board[0][1]->hasMarble)
				)
				{
					$moves[6] = array();
					
					$moves[6][] = array('up', -3, -1);
					$moves[6][] = array('up', -3, 0);
					
					if(!$this->board[-1][1]->hasMarble)
					{
						$moves[6][] = array('up', -3, 1);
						$moves[6][] = array('down', 0, 1);
						$moves[6][] = array('right', -1, -1);
						$moves[6][] = array('up', -2, 1);
					}
					else
					{
						$moves[6][] = array('up', -2, 1);
						$moves[6][] = array('right', -1, -1);
						$moves[6][] = array('down', 0, 1);
						$moves[6][] = array('up', -3, 1);
					}
				}
				
				if(
					!$this->board[1][0]->hasMarble && !$this->board[1][1]->hasMarble &&
					($this->board[-1][-1]->hasMarble && !$this->board[0][-1]->hasMarble) ||
					(!$this->board[-1][-1]->hasMarble && $this->board[0][-1]->hasMarble)
				)
				{
					$moves[7] = array();
					
					$moves[7][] = array('up', -3, 1);
					$moves[7][] = array('up', -3, 0);
					
					if(!$this->board[-1][-1]->hasMarble)
					{
						$moves[7][] = array('up', -3, -1);
						$moves[7][] = array('down', 0, -1);
						$moves[7][] = array('left', -1, 1);
						$moves[7][] = array('up', -2, -1);
					}
					else
					{
						$moves[7][] = array('up', -2, -1);
						$moves[7][] = array('left', -1, 1);
						$moves[7][] = array('down', 0, -1);
						$moves[7][] = array('up', -3, -1);
					}
				}
				
				if($this->board[-1][0]->hasMarble && !$this->board[-1][1]->hasMarble && !$this->board[-1][-2]->hasMarble)
				{
					$moves[8] = array();
					
					$moves[8][] = array('up', -3, -1);
					$moves[8][] = array('left', -1, 0);
					$moves[8][] = array('left', -3, 1);
					$moves[8][] = array('left', -2, 1);
					$moves[8][] = array('up', -3, -1);
					$moves[8][] = array('right', -1, -2);
				}
				
				if(!$this->board[-1][0]->hasMarble && !$this->board[-1][1]->hasMarble && $this->board[-1][-2]->hasMarble)
				{
					$moves[9] = array();
					
					$moves[9][] = array('up', -3, -1);
					$moves[9][] = array('right', -1, -2);
					$moves[9][] = array('left', -3, 1);
					$moves[9][] = array('left', -2, 1);
					$moves[9][] = array('up', -3, -1);
					$moves[9][] = array('left', -1, 0);
				}
				
				if($this->board[-1][0]->hasMarble && !$this->board[-1][1]->hasMarble && !$this->board[-1][2]->hasMarble)
				{
					$moves[10] = array();
					
					$moves[10][] = array('up', -3, 1);
					$moves[10][] = array('right', -1, 0);
					$moves[10][] = array('right', -3, -1);
					$moves[10][] = array('right', -2, -1);
					$moves[10][] = array('up', -3, 1);
					$moves[10][] = array('left', -1, 2);
				}
				
				if(!$this->board[-1][0]->hasMarble && !$this->board[-1][1]->hasMarble && $this->board[-1][2]->hasMarble)
				{
					$moves[11] = array();
					
					$moves[11][] = array('up', -3, 1);
					$moves[11][] = array('left', -1, 2);
					$moves[11][] = array('right', -3, -1);
					$moves[11][] = array('right', -2, -1);
					$moves[11][] = array('up', -3, 1);
					$moves[11][] = array('right', -1, 0);
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Left Area
		try
		{
			if(
				$this->board[1][-3]->hasMarble &&
				$this->board[1][-2]->hasMarble &&
				$this->board[0][-3]->hasMarble &&
				$this->board[0][-2]->hasMarble &&
				$this->board[-1][-3]->hasMarble &&
				$this->board[-1][-2]->hasMarble
			)
			{
				if(
					!$this->board[0][-1]->hasMarble && !$this->board[-1][-1]->hasMarble &&
					($this->board[1][-1]->hasMarble && !$this->board[1][0]->hasMarble) ||
					(!$this->board[1][-1]->hasMarble && $this->board[1][0]->hasMarble)
				)
				{
					$moves[12] = array();
					
					$moves[12][] = array('right', 0, -3);
					$moves[12][] = array('right', -1, -3);
					
					if(!$this->board[1][-1]->hasMarble)
					{
						$moves[12][] = array('right', 1, -3);
						$moves[12][] = array('left', 1, 0);
						$moves[12][] = array('up', -1, -1);
						$moves[12][] = array('right', 1, -2);
					}
					else
					{
						$moves[12][] = array('right', 1, -2);
						$moves[12][] = array('up', -1, -1);
						$moves[12][] = array('left', 1, 0);
						$moves[12][] = array('right', 1, -3);
					}
				}
				
				if(
					!$this->board[0][-1]->hasMarble && !$this->board[1][-1]->hasMarble &&
					($this->board[-1][-1]->hasMarble && !$this->board[-1][0]->hasMarble) ||
					(!$this->board[-1][-1]->hasMarble && $this->board[-1][0]->hasMarble)
				)
				{
					$moves[13] = array();
					
					$moves[13][] = array('right', 0, -3);
					$moves[13][] = array('right', 1, -3);
					
					if(!$this->board[-1][-1]->hasMarble)
					{
						$moves[13][] = array('right', -1, -3);
						$moves[13][] = array('left', -1, 0);
						$moves[13][] = array('down', 1, -1);
						$moves[13][] = array('right', -1, -2);
					}
					else
					{
						$moves[13][] = array('right', -1, -2);
						$moves[13][] = array('down', 1, -1);
						$moves[13][] = array('left', -1, 0);
						$moves[13][] = array('right', -1, -3);
					}
				}
				
				if($this->board[0][-1]->hasMarble && !$this->board[1][-1]->hasMarble && !$this->board[2][-1]->hasMarble)
				{
					$moves[14] = array();
					
					$moves[14][] = array('right', 1, -3);
					$moves[14][] = array('up', 0, -1);
					$moves[14][] = array('up', -1, -3);
					$moves[14][] = array('up', -1, -2);
					$moves[14][] = array('right', 1, -3);
					$moves[14][] = array('down', 2, -1);
				}
				
				if(!$this->board[0][-1]->hasMarble && !$this->board[1][-1]->hasMarble && $this->board[2][-1]->hasMarble)
				{
					$moves[15] = array();
					
					$moves[15][] = array('right', 1, -3);
					$moves[15][] = array('down', 2, -1);
					$moves[15][] = array('up', -1, -3);
					$moves[15][] = array('up', -1, -2);
					$moves[15][] = array('right', 1, -3);
					$moves[15][] = array('up', 0, -1);
				}
				
				if($this->board[0][-1]->hasMarble && !$this->board[-1][-1]->hasMarble && !$this->board[-2][-1]->hasMarble)
				{
					$moves[16] = array();
					
					$moves[16][] = array('right', -1, -3);
					$moves[16][] = array('down', 0, -1);
					$moves[16][] = array('down', 1, -3);
					$moves[16][] = array('down', 1, -2);
					$moves[16][] = array('right', -1, -3);
					$moves[16][] = array('up', -2, -1);
				}
				
				if(!$this->board[0][-1]->hasMarble && !$this->board[-1][-1]->hasMarble && $this->board[-2][-1]->hasMarble)
				{
					$moves[17] = array();
					
					$moves[17][] = array('right', -1, -3);
					$moves[17][] = array('up', -2, -1);
					$moves[17][] = array('down', 1, -3);
					$moves[17][] = array('down', 1, -2);
					$moves[17][] = array('right', -1, -3);
					$moves[17][] = array('down', 0, -1);
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Right Area
		try
		{
			if(
				$this->board[1][3]->hasMarble &&
				$this->board[1][2]->hasMarble &&
				$this->board[0][3]->hasMarble &&
				$this->board[0][2]->hasMarble &&
				$this->board[-1][3]->hasMarble &&
				$this->board[-1][2]->hasMarble
			)
			{
				if(
					!$this->board[0][1]->hasMarble && !$this->board[-1][1]->hasMarble &&
					($this->board[1][1]->hasMarble && !$this->board[1][0]->hasMarble) ||
					(!$this->board[1][1]->hasMarble && $this->board[1][0]->hasMarble)
				)
				{
					$moves[18] = array();
					
					$moves[18][] = array('left', 0, 3);
					$moves[18][] = array('left', -1, 3);
					
					if(!$this->board[1][1]->hasMarble)
					{
						$moves[18][] = array('left', 1, 3);
						$moves[18][] = array('right', 1, 0);
						$moves[18][] = array('up', -1, 1);
						$moves[18][] = array('left', 1, 2);
					}
					else
					{
						$moves[18][] = array('left', 1, 2);
						$moves[18][] = array('up', -1, 1);
						$moves[18][] = array('right', 1, 0);
						$moves[18][] = array('left', 1, 3);
					}
				}
				
				
				if(
					!$this->board[0][1]->hasMarble && !$this->board[1][1]->hasMarble &&
					($this->board[-1][1]->hasMarble && !$this->board[-1][0]->hasMarble) ||
					(!$this->board[-1][1]->hasMarble && $this->board[-1][0]->hasMarble)
				)
				{
					$moves[19] = array();
					
					$moves[19][] = array('left', 0, 3);
					$moves[19][] = array('left', 1, 3);
					
					if(!$this->board[-1][1]->hasMarble)
					{
						$moves[19][] = array('left', -1, 3);
						$moves[19][] = array('right', -1, 0);
						$moves[19][] = array('down', 1, 1);
						$moves[19][] = array('left', -1, 2);
					}
					else
					{
						$moves[19][] = array('left', -1, 2);
						$moves[19][] = array('down', 1, 1);
						$moves[19][] = array('right', -1, 0);
						$moves[19][] = array('left', -1, 3);
					}
				}
				
				
				if($this->board[0][1]->hasMarble && !$this->board[1][1]->hasMarble && !$this->board[2][1]->hasMarble)
				{
					$moves[20] = array();
					
					$moves[20][] = array('left', 1, 3);
					$moves[20][] = array('up', 0, 1);
					$moves[20][] = array('up', -1, 3);
					$moves[20][] = array('up', -1, 2);
					$moves[20][] = array('left', 1, 3);
					$moves[20][] = array('down', 2, 1);
				}
				
				if(!$this->board[0][1]->hasMarble && !$this->board[1][1]->hasMarble && $this->board[2][1]->hasMarble)
				{
					$moves[21] = array();
					
					$moves[21][] = array('left', 1, 3);
					$moves[21][] = array('down', 2, 1);
					$moves[21][] = array('up', -1, 3);
					$moves[21][] = array('up', -1, 2);
					$moves[21][] = array('left', 1, 3);
					$moves[21][] = array('up', 0, 1);
				}
				
				if($this->board[0][1]->hasMarble && !$this->board[-1][1]->hasMarble && !$this->board[-2][1]->hasMarble)
				{
					$moves[22] = array();
					
					$moves[22][] = array('left', -1, 3);
					$moves[22][] = array('down', 0, 1);
					$moves[22][] = array('down', 1, 3);
					$moves[22][] = array('down', 1, 2);
					$moves[22][] = array('left', -1, 3);
					$moves[22][] = array('up', -2, 1);
				}
				
				if(!$this->board[0][1]->hasMarble && !$this->board[-1][1]->hasMarble && $this->board[-2][1]->hasMarble)
				{
					$moves[23] = array();
					
					$moves[23][] = array('left', -1, 3);
					$moves[23][] = array('up', -2, 1);
					$moves[23][] = array('down', 1, 3);
					$moves[23][] = array('down', 1, 2);
					$moves[23][] = array('left', -1, 3);
					$moves[23][] = array('down', 0, 1);
				}
			}
		}
		catch(Exception $e) {};
		
		return $moves;
	}
	
	
	/**
	 * Find a move for a 4-holes line.
	 * 
	 * Representation of a 4-holes line:
	 * 
	 *  X X O X
	 * 
	 * @return array 	Possible moves
	 */
	private function findLineMove()
	{
		$moves = array();
		
		for($i = 3; $i >= -3; $i--)
		{
			for($j = -3; $j <= 3; $j++)
			{
				$hole = & $this->board[$i][$j];
				
				if(	$hole )
				{
					if($hole->hasMarble)
					{
						// Top
						if(
							$this->canMoveTo('up', $i, $j) &&
							!empty($this->board[$i + 3][$j]) &&
							$this->board[$i + 3][$j]->hasMarble
						)
						{
							$moves[0] = array();
							$moves[0][] = array('up', $i, $j);
							$moves[0][] = array('down', $i + 3, $j);
						}
						
						// Right
						if(
							$this->canMoveTo('right', $i, $j) &&
							!empty($this->board[$i][$j + 3]) &&
							$this->board[$i][$j + 3]->hasMarble
						)
						{
							$moves[1] = array();
							$moves[1][] = array('right', $i, $j);
							$moves[1][] = array('left', $i, $j + 3);
						}
						
						// Down
						if(
							$this->canMoveTo('down', $i, $j) &&
							!empty($this->board[$i - 3][$j]) &&
							$this->board[$i - 3][$j]->hasMarble
						)
						{
							$moves[2] = array();
							$moves[2][] = array('down', $i, $j);
							$moves[2][] = array('top', $i - 3, $j);
						}
						
						// Left
						if(
							$this->canMoveTo('left', $i, $j) &&
							!empty($this->board[$i][$j - 3]) &&
							$this->board[$i][$j - 3]->hasMarble
						)
						{
							$moves[3] = array();
							$moves[3][] = array('left', $i, $j);
							$moves[3][] = array('right', $i, $j - 3);
						}
					}
				}
			}
		}
		
		return $moves;
	}	
	
	/**
	 * Find a simple move to make.
	 *
	 * @return array 	Possible moves.
	 */
	private function findSimpleMove()
	{
		$moves = array();
		
		for($i = 3; $i >= -3; $i--)
		{
			for($j = -3; $j <= 3; $j++)
			{
				$hole = & $this->board[$i][$j];
				
				if(	$hole )
				{
					if($hole->hasMarble)
					{
						foreach($this->moveDestinations as $direction)
						{
							if($this->canMoveTo($direction, $i, $j))
							{
								$moves[] = array(array($direction, $i, $j));
							}
						}
					}
				}
			}
		}
		
		return $moves;
	}
}


class PegPlayer
{
	public function play()
	{
		ini_set('memory_limit', '-1');
		set_time_limit('180');
		
		
		$winners = array();
		
		$peg = new Peg();
		$nextPegs = $peg->findPath();
		unset($peg);
		
		while(!empty($nextPegs))
		{
			$pegs = $nextPegs;
			
			unset($nextPegs);
			$nextPegs = array();
			
			foreach($pegs as $peg)
			{
				$tmp = $peg->findPath();
				
				if(empty($tmp) && count($peg->moves) > 30)
				{
					$winners[] = clone $peg;
				}
				else
				{
					unset($peg);
					
					foreach($tmp as $t)
					{
						$nextPegs[] = clone $t;
						unset($t);
					}
				}
				unset($tmp);
			}
			
			unset($pegs);
		}
		
		return $winners;
	}
}

