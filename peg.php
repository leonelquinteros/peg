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
	private $dbPassword = '';
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
 * Plays Peg solitaire game.
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
	private $moves;
	
	/**
	 * Indicates if the game should render each move result.
	 * @var boolean
	 */
	public $renderPlay = false;
	
	/**
	 * Using this attribute can switch between a fixed winning game and a random game where you can win or not.
	 * @var boolean
	 */
	public $playToWin = true;
	
	
	public function __construct()
	{
		$this->moveDestinations = array('up', 'right', 'down', 'left');
		$this->resetBoard();
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
	 * Starts the game and play until there's no mor available movements.
	 */
	public function play()
	{
		if($this->renderPlay)
		{
			echo $this->renderBoard();
		}
		
		if($this->playToWin)
		{
			$this->findLMove();
			$this->findLMove();
		}
		
		while($this->getAvailableMoves())
		{
			$this->findMove();
		}
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
			if(	($jump && $jump->hasMarble) && ($dest && !$dest->hasMarble) )
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
	 * Find next move.
	 * If playing to win, finds movements in a convinient order. Otherwise, randomizes the choices.
	 */
	private function findMove()
	{
		if( ($this->playToWin || rand(0,1)) && $this->findRectangleMove())
		{
			return true;
		}
		
		if( ($this->playToWin || rand(0,1)) && $this->findBigLMove())
		{
			return true;
		}
		
		if(($this->playToWin || rand(0,1)) && $this->findLMove())
		{
			return true;
		}
		
		if(($this->playToWin || rand(0,1)) && $this->findSimpleMove())
		{
			return true;
		}
		
		return false;
	}
	
	
	/**
	 * Find moves available for Bog L blocks.
	 *
	 * Representation of a Big L block:
	 *
	 *     X
	 *   X X O
	 *     X
	 *     X X X
	 *
	 */
	private function findBigLMove()
	{
		// Top
		try
		{
			if(	($this->playToWin || rand(0,1)) &&
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
					if($this->board[1][-2]->hasMarble && !$this->board[1][0]->hasMarble)
					{
						$this->moveTo('right', 1, -2);
					}
					else
					{
						$this->moveTo('left', 1, 0);
					}
					
					$this->moveTo('down', 3, -1);
					$this->moveTo('left', 3, 1);
					$this->moveTo('up', 0, -1);
					$this->moveTo('down', 3, -1);
					
					if($this->board[1][-2]->hasMarble && !$this->board[1][0]->hasMarble)
					{
						$this->moveTo('right', 1, -2);
					}
					else
					{
						$this->moveTo('left', 1, 0);
					}
					
					return true;
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
					if($this->board[1][2]->hasMarble && !$this->board[1][0]->hasMarble)
					{
						$this->moveTo('left', 1, 2);
					}
					else
					{
						$this->moveTo('right', 1, 0);
					}
					
					$this->moveTo('down', 3, 1);
					$this->moveTo('right', 3, -1);
					$this->moveTo('up', 0, 1);
					$this->moveTo('down', 3, 1);
					
					if($this->board[1][2]->hasMarble && !$this->board[1][0]->hasMarble)
					{
						$this->moveTo('left', 1, 2);
					}
					else
					{
						$this->moveTo('right', 1, 0);
					}
					
					return true;
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Down
		try
		{
			if(	($this->playToWin || rand(0,1)) &&
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
					if($this->board[-1][-2]->hasMarble && !$this->board[-1][0]->hasMarble)
					{
						$this->moveTo('right', -1, -2);
					}
					else
					{
						$this->moveTo('left', -1, 0);
					}
					
					$this->moveTo('up', -3, -1);
					$this->moveTo('left', -3, 1);
					$this->moveTo('down', 0, -1);
					$this->moveTo('up', -3, -1);
					
					if($this->board[-1][-2]->hasMarble && !$this->board[-1][0]->hasMarble)
					{
						$this->moveTo('right', -1, -2);
					}
					else
					{
						$this->moveTo('left', -1, 0);
					}
					
					return true;
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
					if($this->board[-1][2]->hasMarble && !$this->board[-1][0]->hasMarble)
					{
						$this->moveTo('left', -1, 2);
					}
					else
					{
						$this->moveTo('right', -1, 0);
					}
					
					$this->moveTo('up', -3, 1);
					$this->moveTo('right', -3, -1);
					$this->moveTo('down', 0, 1);
					$this->moveTo('up', -3, 1);
					
					if($this->board[-1][2]->hasMarble && !$this->board[-1][0]->hasMarble)
					{
						$this->moveTo('left', -1, 2);
					}
					else
					{
						$this->moveTo('right', -1, 0);
					}
					
					return true;
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Left
		try
		{
			if(	($this->playToWin || rand(0,1)) &&
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
					if($this->board[2][-1]->hasMarble && !$this->board[0][-1]->hasMarble)
					{
						$this->moveTo('down', 2, -1);
					}
					else
					{
						$this->moveTo('up', 0, -1);
					}
					
					$this->moveTo('right', 1, -3);
					$this->moveTo('up', -1, -3);
					$this->moveTo('left', 1, 0);
					$this->moveTo('right', 1, -3);
					
					if($this->board[2][-1]->hasMarble && !$this->board[0][-1]->hasMarble)
					{
						$this->moveTo('down', 2, -1);
					}
					else
					{
						$this->moveTo('up', 0, -1);
					}
					
					return true;
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
					if($this->board[-2][-1]->hasMarble && !$this->board[0][-1]->hasMarble)
					{
						$this->moveTo('up', -2, -1);
					}
					else
					{
						$this->moveTo('down', 0, -1);
					}
					
					$this->moveTo('right', -1, -3);
					$this->moveTo('down', 1, -3);
					$this->moveTo('left', 1, 0);
					$this->moveTo('right', -1, -3);
					
					if($this->board[-2][-1]->hasMarble && !$this->board[0][-1]->hasMarble)
					{
						$this->moveTo('up', 2, -1);
					}
					else
					{
						$this->moveTo('down', 0, -1);
					}
					
					return true;
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Right
		try
		{
			if(	($this->playToWin || rand(0,1)) &&
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
					if($this->board[2][1]->hasMarble && !$this->board[0][1]->hasMarble)
					{
						$this->moveTo('down', 2, 1);
					}
					else
					{
						$this->moveTo('up', 0, 1);
					}
					
					$this->moveTo('left', 1, 3);
					$this->moveTo('up', -1, 3);
					$this->moveTo('right', 1, 0);
					$this->moveTo('left', 1, 3);
					
					if($this->board[2][1]->hasMarble && !$this->board[0][1]->hasMarble)
					{
						$this->moveTo('down', 2, 1);
					}
					else
					{
						$this->moveTo('up', 0, 1);
					}
					
					return true;
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
					if($this->board[-2][1]->hasMarble && !$this->board[0][1]->hasMarble)
					{
						$this->moveTo('up', -2, 1);
					}
					else
					{
						$this->moveTo('down', 0, 1);
					}
					
					$this->moveTo('left', -1, 3);
					$this->moveTo('down', 1, 3);
					$this->moveTo('right', -1, 0);
					$this->moveTo('left', -1, 3);
					
					if($this->board[-2][1]->hasMarble && !$this->board[0][1]->hasMarble)
					{
						$this->moveTo('up', -2, 1);
					}
					else
					{
						$this->moveTo('down', 0, 1);
					}
					
					return true;
				}
			}
		}
		catch(Exception $e) {};
		
		
		return false;
	}
	
	
	/**
	 * Find available moves for L block.
	 *
	 * Representation of L block:
	 *
	 *         O
	 *     X X X
	 *         X
	 */
	private function findLMove()
	{
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
							($this->playToWin || rand(0,1)) &&
							@$this->board[$i][$j + 1]->hasMarble &&
							@$this->board[$i][$j + 2]->hasMarble &&
							@$this->board[$i - 1][$j + 1]->hasMarble &&
							@$this->board[$i - 2][$j + 1]->hasMarble
						)
						{
							$this->moveTo('left', $i, $j + 2);
							$this->moveTo('up', $i - 2, $j + 1);
							$this->moveTo('right', $i, $j);
							
							return true;
						}
					}
					catch(Exception $e) {} // Not available
					
					/**
					 * The order of the search it's made for winning purposes. 
					 * If play to win, the second move should avoid repeat a L move on the center hole- 
					 */
					if($this->playToWin)
					{
						if($i == 0 && $j == 0)
						{
							continue;
						}
					}
					
					try
					{
						// Top Left
						if(
							($this->playToWin || rand(0,1)) &&
							@$this->board[$i + 1][$j]->hasMarble &&
							@$this->board[$i + 2][$j]->hasMarble &&
							@$this->board[$i + 1][$j - 1]->hasMarble &&
							@$this->board[$i + 1][$j - 2]->hasMarble
						)
						{
							$this->moveTo('down', $i + 2, $j);
							$this->moveTo('right', $i + 1, $j - 2);
							$this->moveTo('up', $i, $j);
							
							return true;
						}
					}
					catch(Exception $e) {} // Not available
					
					
					try
					{
						// Down Right
						if(
							($this->playToWin || rand(0,1)) &&
							@$this->board[$i - 1][$j]->hasMarble &&
							@$this->board[$i - 2][$j]->hasMarble &&
							@$this->board[$i - 1][$j + 1]->hasMarble &&
							@$this->board[$i - 1][$j + 2]->hasMarble
						)
						{
							$this->moveTo('up', $i - 2, $j);
							$this->moveTo('left', $i - 1, $j + 2);
							$this->moveTo('down', $i, $j);
							
							return true;
						}
					}
					catch(Exception $e) {}// Not available
					
					try
					{
						// Down Left
						if(
							($this->playToWin || rand(0,1)) &&
							@$this->board[$i - 1][$j]->hasMarble &&
							@$this->board[$i - 2][$j]->hasMarble &&
							@$this->board[$i - 1][$j - 1]->hasMarble &&
							@$this->board[$i - 1][$j - 2]->hasMarble
						)
						{
							$this->moveTo('up', $i - 2, $j);
							$this->moveTo('right', $i - 1, $j - 2);
							$this->moveTo('down', $i, $j);
							
							return true;
						}
					}
					catch(Exception $e) {}// Not available
					
					try
					{
						// Top Right
						if(
							($this->playToWin || rand(0,1)) &&
							@$this->board[$i + 1][$j]->hasMarble &&
							@$this->board[$i + 2][$j]->hasMarble &&
							@$this->board[$i + 1][$j + 1]->hasMarble &&
							@$this->board[$i + 1][$j + 2]->hasMarble
						)
						{
							$this->moveTo('down', $i + 2, $j);
							$this->moveTo('left', $i + 1, $j + 2);
							$this->moveTo('up', $i, $j);
							
							return true;
						}
					}
					catch(Exception $e) {} // Not available
					
					try
					{
						// Right Top
						if(
							($this->playToWin || rand(0,1)) &&
							@$this->board[$i][$j + 1]->hasMarble &&
							@$this->board[$i][$j + 2]->hasMarble &&
							@$this->board[$i + 1][$j + 1]->hasMarble &&
							@$this->board[$i + 2][$j + 1]->hasMarble
						)
						{
							$this->moveTo('left', $i, $j + 2);
							$this->moveTo('down', $i + 2, $j + 1);
							$this->moveTo('right', $i, $j);
							
							return true;
						}
					}
					catch(Exception $e) {}// Not available
					
					try
					{
						// Left Top
						if(
							($this->playToWin || rand(0,1)) &&
							@$this->board[$i][$j - 1]->hasMarble &&
							@$this->board[$i][$j - 2]->hasMarble &&
							@$this->board[$i + 1][$j - 1]->hasMarble &&
							@$this->board[$i + 2][$j - 1]->hasMarble
						)
						{
							$this->moveTo('right', $i, $j - 2);
							$this->moveTo('down', $i + 2, $j - 1);
							$this->moveTo('left', $i, $j);
							
							return true;
						}
					}
					catch(Exception $e) {}// Not available
					
					try
					{
						// Left Down
						if(
							($this->playToWin || rand(0,1)) &&
							@$this->board[$i][$j - 1]->hasMarble &&
							@$this->board[$i][$j - 2]->hasMarble &&
							@$this->board[$i - 1][$j - 1]->hasMarble &&
							@$this->board[$i - 2][$j - 1]->hasMarble
						)
						{
							$this->moveTo('right', $i, $j - 2);
							$this->moveTo('up', $i - 2, $j - 1);
							$this->moveTo('left', $i, $j);
							
							return true;
						}
					}
					catch(Exception $e) {}// Not available
				}
			}
		}
		
		return false;
	}
	
	
	/**
	 * Find available moves for a rectangle block.
	 *
	 * Representation of a rectangle block:
	 *
	 *   X O O O
	 *     X X X
	 *     X X X
	 */
	private function findRectangleMove()
	{
		// Top Area
		try
		{
			if(
				($this->playToWin || rand(0,1)) &&
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
					$this->moveTo('down', 3, -1);
					$this->moveTo('down', 3, 0);
					
					if(!$this->board[1][1]->hasMarble)
					{
						$this->moveTo('down', 3, 1);
						$this->moveTo('up', 0, 1);
						$this->moveTo('right', 1, -1);
						$this->moveTo('down', 2, 1);
					}
					else
					{
						$this->moveTo('down', 2, 1);
						$this->moveTo('right', 1, -1);
						$this->moveTo('up', 0, 1);
						$this->moveTo('down', 3, 1);
					}
				}
				
				if(
					!$this->board[1][0]->hasMarble && !$this->board[1][1]->hasMarble &&
					($this->board[1][-1]->hasMarble && !$this->board[0][-1]->hasMarble) ||
					(!$this->board[1][-1]->hasMarble && $this->board[0][-1]->hasMarble)
				)
				{
					$this->moveTo('down', 3, 1);
					$this->moveTo('down', 3, 0);
					
					if(!$this->board[1][-1]->hasMarble)
					{
						$this->moveTo('down', 3, -1);
						$this->moveTo('up', 0, -1);
						$this->moveTo('left', 1, 1);
						$this->moveTo('down', 2, -1);
					}
					else
					{
						$this->moveTo('down', 2, -1);
						$this->moveTo('left', 1, 1);
						$this->moveTo('up', 0, -1);
						$this->moveTo('down', 3, -1);
					}
				}
				
				
				if($this->board[1][0]->hasMarble && !$this->board[1][-1]->hasMarble && !$this->board[1][-2]->hasMarble)
				{
					$this->moveTo('down', 3, -1);
					$this->moveTo('left', 1, 0);
					$this->moveTo('left', 3, 1);
					$this->moveTo('left', 2, 1);
					$this->moveTo('down', 3, -1);
					$this->moveTo('right', 1, -2);
					
					return true;
				}
				
				if(!$this->board[1][0]->hasMarble && !$this->board[1][-1]->hasMarble && $this->board[1][-2]->hasMarble)
				{
					$this->moveTo('down', 3, -1);
					$this->moveTo('right', 1, -2);
					$this->moveTo('left', 3, 1);
					$this->moveTo('left', 2, 1);
					$this->moveTo('down', 3, -1);
					$this->moveTo('left', 1, 0);
					
					return true;
				}
				
				if($this->board[1][0]->hasMarble && !$this->board[1][1]->hasMarble && !$this->board[1][2]->hasMarble)
				{
					$this->moveTo('down', 3, 1);
					$this->moveTo('right', 1, 0);
					$this->moveTo('right', 3, -1);
					$this->moveTo('right', 2, -1);
					$this->moveTo('down', 3, 1);
					$this->moveTo('left', 1, 2);
					
					return true;
				}
				
				if(!$this->board[1][0]->hasMarble && !$this->board[1][1]->hasMarble && $this->board[1][2]->hasMarble)
				{
					$this->moveTo('down', 3, 1);
					$this->moveTo('left', 1, 2);
					$this->moveTo('right', 3, -1);
					$this->moveTo('right', 2, -1);
					$this->moveTo('down', 3, 1);
					$this->moveTo('right', 1, 0);
					
					return true;
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Down Area
		try
		{
			if(
				($this->playToWin || rand(0,1)) &&
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
					$this->moveTo('up', -3, -1);
					$this->moveTo('up', -3, 0);
					
					if(!$this->board[-1][1]->hasMarble)
					{
						$this->moveTo('up', -3, 1);
						$this->moveTo('down', 0, 1);
						$this->moveTo('right', -1, -1);
						$this->moveTo('up', -2, 1);
					}
					else
					{
						$this->moveTo('up', -2, 1);
						$this->moveTo('right', -1, -1);
						$this->moveTo('down', 0, 1);
						$this->moveTo('up', -3, 1);
					}
				}
				
				if(
					!$this->board[1][0]->hasMarble && !$this->board[1][1]->hasMarble &&
					($this->board[-1][-1]->hasMarble && !$this->board[0][-1]->hasMarble) ||
					(!$this->board[-1][-1]->hasMarble && $this->board[0][-1]->hasMarble)
				)
				{
					$this->moveTo('up', -3, 1);
					$this->moveTo('up', -3, 0);
					
					if(!$this->board[-1][-1]->hasMarble)
					{
						$this->moveTo('up', -3, -1);
						$this->moveTo('down', 0, -1);
						$this->moveTo('left', -1, 1);
						$this->moveTo('up', -2, -1);
					}
					else
					{
						$this->moveTo('up', -2, -1);
						$this->moveTo('left', -1, 1);
						$this->moveTo('down', 0, -1);
						$this->moveTo('up', -3, -1);
					}
				}
				
				if($this->board[-1][0]->hasMarble && !$this->board[-1][1]->hasMarble && !$this->board[-1][-2]->hasMarble)
				{
					$this->moveTo('up', -3, -1);
					$this->moveTo('left', -1, 0);
					$this->moveTo('left', -3, 1);
					$this->moveTo('left', -2, 1);
					$this->moveTo('up', -3, -1);
					$this->moveTo('right', -1, -2);
					
					return true;
				}
				
				if(!$this->board[-1][0]->hasMarble && !$this->board[-1][1]->hasMarble && $this->board[-1][-2]->hasMarble)
				{
					$this->moveTo('up', -3, -1);
					$this->moveTo('right', -1, -2);
					$this->moveTo('left', -3, 1);
					$this->moveTo('left', -2, 1);
					$this->moveTo('up', -3, -1);
					$this->moveTo('left', -1, 0);
					
					return true;
				}
				
				if($this->board[-1][0]->hasMarble && !$this->board[-1][1]->hasMarble && !$this->board[-1][2]->hasMarble)
				{
					$this->moveTo('up', -3, 1);
					$this->moveTo('right', -1, 0);
					$this->moveTo('right', -3, -1);
					$this->moveTo('right', -2, -1);
					$this->moveTo('up', -3, 1);
					$this->moveTo('left', -1, 2);
					
					return true;
				}
				
				if(!$this->board[-1][0]->hasMarble && !$this->board[-1][1]->hasMarble && $this->board[-1][2]->hasMarble)
				{
					$this->moveTo('up', -3, 1);
					$this->moveTo('left', -1, 2);
					$this->moveTo('right', -3, -1);
					$this->moveTo('right', -2, -1);
					$this->moveTo('up', -3, 1);
					$this->moveTo('right', -1, 0);
					
					return true;
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Left Area
		try
		{
			if(
				($this->playToWin || rand(0,1)) &&
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
					$this->moveTo('right', 0, -3);
					$this->moveTo('right', -1, -3);
					
					if(!$this->board[1][-1]->hasMarble)
					{
						$this->moveTo('right', 1, -3);
						$this->moveTo('left', 1, 0);
						$this->moveTo('up', -1, -1);
						$this->moveTo('right', 1, -2);
					}
					else
					{
						$this->moveTo('right', 1, -2);
						$this->moveTo('up', -1, -1);
						$this->moveTo('left', 1, 0);
						$this->moveTo('right', 1, -3);
					}
				}
				
				if(
					!$this->board[0][-1]->hasMarble && !$this->board[1][-1]->hasMarble &&
					($this->board[-1][-1]->hasMarble && !$this->board[-1][0]->hasMarble) ||
					(!$this->board[-1][-1]->hasMarble && $this->board[-1][0]->hasMarble)
				)
				{
					$this->moveTo('right', 0, -3);
					$this->moveTo('right', 1, -3);
					
					if(!$this->board[-1][-1]->hasMarble)
					{
						$this->moveTo('right', -1, -3);
						$this->moveTo('left', -1, 0);
						$this->moveTo('down', 1, -1);
						$this->moveTo('right', -1, -2);
					}
					else
					{
						$this->moveTo('right', -1, -2);
						$this->moveTo('down', 1, -1);
						$this->moveTo('left', -1, 0);
						$this->moveTo('right', -1, -3);
					}
				}
				
				if($this->board[0][-1]->hasMarble && !$this->board[1][-1]->hasMarble && !$this->board[2][-1]->hasMarble)
				{
					$this->moveTo('right', 1, -3);
					$this->moveTo('up', 0, -1);
					$this->moveTo('up', -1, -3);
					$this->moveTo('up', -1, -2);
					$this->moveTo('right', 1, -3);
					$this->moveTo('down', 2, -1);
					
					return true;
				}
				
				if(!$this->board[0][-1]->hasMarble && !$this->board[1][-1]->hasMarble && $this->board[2][-1]->hasMarble)
				{
					$this->moveTo('right', 1, -3);
					$this->moveTo('down', 2, -1);
					$this->moveTo('up', -1, -3);
					$this->moveTo('up', -1, -2);
					$this->moveTo('right', 1, -3);
					$this->moveTo('up', 0, -1);
					
					return true;
				}
				
				if($this->board[0][-1]->hasMarble && !$this->board[-1][-1]->hasMarble && !$this->board[-2][-1]->hasMarble)
				{
					$this->moveTo('right', -1, -3);
					$this->moveTo('down', 0, -1);
					$this->moveTo('down', 1, -3);
					$this->moveTo('down', 1, -2);
					$this->moveTo('right', -1, -3);
					$this->moveTo('up', -2, -1);
					
					return true;
				}
				
				if(!$this->board[0][-1]->hasMarble && !$this->board[-1][-1]->hasMarble && $this->board[-2][-1]->hasMarble)
				{
					$this->moveTo('right', -1, -3);
					$this->moveTo('up', -2, -1);
					$this->moveTo('down', 1, -3);
					$this->moveTo('down', 1, -2);
					$this->moveTo('right', -1, -3);
					$this->moveTo('down', 0, -1);
					
					return true;
				}
			}
		}
		catch(Exception $e) {};
		
		
		// Right Area
		try
		{
			if(
				($this->playToWin || rand(0,1)) &&
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
					$this->moveTo('left', 0, 3);
					$this->moveTo('left', -1, 3);
					
					if(!$this->board[1][1]->hasMarble)
					{
						$this->moveTo('left', 1, 3);
						$this->moveTo('right', 1, 0);
						$this->moveTo('up', -1, 1);
						$this->moveTo('left', 1, 2);
					}
					else
					{
						$this->moveTo('left', 1, 2);
						$this->moveTo('up', -1, 1);
						$this->moveTo('right', 1, 0);
						$this->moveTo('left', 1, 3);
					}
				}
				
				
				if(
					!$this->board[0][1]->hasMarble && !$this->board[1][1]->hasMarble &&
					($this->board[-1][1]->hasMarble && !$this->board[-1][0]->hasMarble) ||
					(!$this->board[-1][1]->hasMarble && $this->board[-1][0]->hasMarble)
				)
				{
					$this->moveTo('left', 0, 3);
					$this->moveTo('left', 1, 3);
					
					if(!$this->board[-1][1]->hasMarble)
					{
						$this->moveTo('left', -1, 3);
						$this->moveTo('right', -1, 0);
						$this->moveTo('down', 1, 1);
						$this->moveTo('left', -1, 2);
					}
					else
					{
						$this->moveTo('left', -1, 2);
						$this->moveTo('down', 1, 1);
						$this->moveTo('right', -1, 0);
						$this->moveTo('left', -1, 3);
					}
					
					
				}
				
				
				if($this->board[0][1]->hasMarble && !$this->board[1][1]->hasMarble && !$this->board[2][1]->hasMarble)
				{
					$this->moveTo('left', 1, 3);
					$this->moveTo('up', 0, 1);
					$this->moveTo('up', -1, 3);
					$this->moveTo('up', -1, 2);
					$this->moveTo('left', 1, 3);
					$this->moveTo('down', 2, 1);
					
					return true;
				}
				
				if(!$this->board[0][1]->hasMarble && !$this->board[1][1]->hasMarble && $this->board[2][1]->hasMarble)
				{
					$this->moveTo('left', 1, 3);
					$this->moveTo('down', 2, 1);
					$this->moveTo('up', -1, 3);
					$this->moveTo('up', -1, 2);
					$this->moveTo('left', 1, 3);
					$this->moveTo('up', 0, 1);
					
					return true;
				}
				
				if($this->board[0][1]->hasMarble && !$this->board[-1][1]->hasMarble && !$this->board[-2][1]->hasMarble)
				{
					$this->moveTo('left', -1, 3);
					$this->moveTo('down', 0, 1);
					$this->moveTo('down', 1, 3);
					$this->moveTo('down', 1, 2);
					$this->moveTo('left', -1, 3);
					$this->moveTo('up', -2, 1);
					
					return true;
				}
				
				if(!$this->board[0][1]->hasMarble && !$this->board[-1][1]->hasMarble && $this->board[-2][1]->hasMarble)
				{
					$this->moveTo('left', -1, 3);
					$this->moveTo('up', -2, 1);
					$this->moveTo('down', 1, 3);
					$this->moveTo('down', 1, 2);
					$this->moveTo('left', -1, 3);
					$this->moveTo('down', 0, 1);
					
					return true;
				}
			}
		}
		catch(Exception $e) {};
		
		
		return false;
	}
	
	
	/**
	 * Find a simple move to make.
	 */
	private function findSimpleMove()
	{
		$availableMoves = $this->getAvailableMoves();
		
		if(count($availableMoves) > 0)
		{
			$move = $availableMoves[rand(0, count($availableMoves) -1)];
			
			$this->move($move[0], $move[1]);
			
			return true;
		}
		
		return false;
	}
	
	
	/**
	 * Gets all the available moves.
	 */
	private function getAvailableMoves()
	{
		$availableMoves = array();
		
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
							$dest = $this->canMoveTo($direction, $i, $j);
							if($dest)
							{
								$availableMoves[] = array( array($i, $j), $dest);
							}
						}
					}
				}
			}
		}
		
		return $availableMoves;
	}
}
