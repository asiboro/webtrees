<?php
namespace Fisharebest\Webtrees;

/**
 * webtrees: online genealogy
 * Copyright (C) 2015 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Class FamilyBookController - Controller for the familybook chart
 */
class FamilyBookController extends ChartController {
	// Data for the view
	public $pid;

	/** @var int Whether to show full details in the individual boxes */
	public $show_full;

	/** @var int Whether to show spouse details */
	public $show_spouse;

	/** @var int Number of descendancy generations to show */
	public $descent;

	/** @var int Number of ascendancy generations to show */
	public $generations;

	/** @var int Size of boxes (percentage) */
	public $box_width;

	/** @var int Number of descendancy generations that exist */
	private $dgenerations;

	/**
	 * Create a family-book controller
	 */
	public function __construct() {
		global $WT_TREE;

		parent::__construct();

		// Extract the request parameters
		$this->show_full   = Filter::getInteger('show_full', 0, 1, $WT_TREE->getPreference('PEDIGREE_FULL_DETAILS'));
		$this->show_spouse = Filter::getInteger('show_spouse', 0, 1);
		$this->descent     = Filter::getInteger('descent', 0, 9, 5);
		$this->generations = Filter::getInteger('generations', 2, $WT_TREE->getPreference('MAX_DESCENDANCY_GENERATIONS'), 2);
		$this->box_width   = Filter::getInteger('box_width', 50, 300, 100);

		// Box sizes are set globally in the theme.  Modify them here.
		global $bwidth, $bheight, $Dbwidth, $bhalfheight, $Dbheight;
		$Dbwidth = $this->box_width * $bwidth / 100;
		$bwidth = $Dbwidth;
		$bheight = $Dbheight;

		// -- adjust size of the compact box
		if (!$this->show_full) {
			$bwidth = $this->box_width * Theme::theme()->parameter('compact-chart-box-x') / 100;
			$bheight = Theme::theme()->parameter('compact-chart-box-y');
		}
		$bhalfheight = $bheight / 2;
		if ($this->root && $this->root->canShowName()) {
			$this->setPageTitle(
				/* I18N: %s is an individual’s name */
				I18N::translate('Family book of %s', $this->root->getFullName())
			);
		} else {
			$this->setPageTitle(I18N::translate('Family book'));
		}
		//Checks how many generations of descendency is for the person for formatting purposes
		$this->dgenerations = $this->maxDescendencyGenerations($this->pid, 0);
		if ($this->dgenerations < 1) {
			$this->dgenerations = 1;
		}
	}

	/**
	 * Prints descendency of passed in person
	 *
	 * @param Individual|null $person
	 * @param integer         $generation
	 *
	 * @return integer
	 */
	private function printDescendency(Individual $person = null, $generation) {
		global $bwidth, $bheight, $show_full, $box_width; // print_pedigree_person() requires these globals.

		if ($generation > $this->dgenerations) {
			return 0;
		}

		$show_full = $this->show_full;
		$box_width = $this->box_width;

		echo '<table><tr><td width="', $bwidth, '">';
		$numkids = 0;

		// Load children
		$children = array();
		if ($person) {
			// Count is position from center to left, dgenerations is number of generations
			if ($generation < $this->dgenerations) {
				// All children, from all partners
				foreach ($person->getSpouseFamilies() as $family) {
					foreach ($family->getChildren() as $child) {
						$children[] = $child;
					}
				}
			}
		}
		if ($generation < $this->dgenerations) {
			if ($children) {
				// real people
				echo '<table>';
				foreach ($children as $i => $child) {
					echo '<tr><td>';
					$kids = $this->printDescendency($child, $generation + 1);
					$numkids += $kids;
					echo '</td>';
					// Print the lines
					if (count($children) > 1) {
						if ($i === 0) {
							// Adjust for the first column on left
							$h = round(((($bheight) * $kids) + 8) / 2); // Assumes border = 1 and padding = 3
							//  Adjust for other vertical columns
							if ($kids > 1) {
								$h = ($kids - 1) * 4 + $h;
							}
							echo '<td class="tdbot">',
							'<img class="tvertline" id="vline_', $child->getXref(), '" src="', Theme::theme()->parameter('image-vline'), '"  height="', $h - 1, '" alt=""></td>';
						} elseif ($i === count($children) - 1) {
							// Adjust for the first column on left
							$h = round(((($bheight) * $kids) + 8) / 2);
							// Adjust for other vertical columns
							if ($kids > 1) {
								$h = ($kids - 1) * 4 + $h;
							}
							echo '<td class="tdtop">',
							'<img class="bvertline" id="vline_', $child->getXref(), '" src="', Theme::theme()->parameter('image-vline'), '" height="', $h + 1, '" alt=""></td>';
						} else {
							echo '<td style="background: url(', Theme::theme()->parameter('image-vline'), ');">',
							'<img class="spacer" src="', Theme::theme()->parameter('image-spacer'), '" alt=""></td>';
						}
					}
					echo '</tr>';
				}
				echo '</table>';
			} else {
				// Hidden/empty boxes - to preserve the layout
				echo '<table><tr><td>';
				$numkids += $this->printDescendency(null, $generation + 1);
				echo '</td></tr></table>';
			}
			echo '</td>';
			echo '<td width="', $bwidth, '">';
		}

		if ($numkids === 0) {
			$numkids = 1;
		}
		echo '<table><tr><td>';
		if ($person) {
			print_pedigree_person($person);
			echo '</td><td>',
			'<img class="line2" src="', Theme::theme()->parameter('image-hline'), '" width="8" height="3" alt="">';
		} else {
			echo '<div style="width:', $bwidth + 19, 'px; height:', $bheight + 8, 'px;"></div>',
			'</td><td>';
		}

		// Print the spouse
		if ($generation === 1) {
			if ($this->show_spouse) {
				foreach ($person->getSpouseFamilies() as $family) {
					$spouse = $family->getSpouse($person);
					echo '</td></tr><tr><td>';
					//-- shrink the box for the spouses
					$tempw = $bwidth;
					$temph = $bheight;
					$bwidth -= 5;
					print_pedigree_person($spouse);
					$bwidth  = $tempw;
					$bheight = $temph;
					$numkids += 0.95;
					echo '</td><td>';
				}
			}
		}
		echo '</td></tr></table>';
		echo '</td></tr>';
		echo '</table>';

		return $numkids;
	}

	/**
	 * Prints pedigree of the person passed in
	 *
	 * @param Individual $person
	 * @param integer       $count
	 */
	private function printPersonPedigree($person, $count) {
		global $bheight, $bwidth, $bhalfheight;
		if ($count >= $this->generations) {
			return;
		}

		$genoffset = $this->generations; // handle pedigree n generations lines
		//-- calculate how tall the lines should be
		$lh = ($bhalfheight + 4) * pow(2, ($genoffset - $count - 1));
		//
		//Prints empty table columns for children w/o parents up to the max generation
		//This allows vertical line spacing to be consistent
		if (count($person->getChildFamilies()) == 0) {
			echo '<table>';
			$this->printEmptyBox($bwidth, $bheight);

			//-- recursively get the father’s family
			$this->printPersonPedigree($person, $count + 1);
			echo '</td><td></tr>';
			$this->printEmptyBox($bwidth, $bheight);

			//-- recursively get the mother’s family
			$this->printPersonPedigree($person, $count + 1);
			echo '</td><td></tr></table>';
		}

		// Empty box section done, now for regular pedigree
		foreach ($person->getChildFamilies() as $family) {
			echo '<table><tr><td class="tdbot">';
			// Determine line height for two or more spouces
			// And then adjust the vertical line for the root person only
			$famcount = 0;
			if ($this->show_spouse) {
				// count number of spouses
				$famcount += count($person->getSpouseFamilies());
			}
			$savlh = $lh; // Save current line height
			if ($count == 1 && $genoffset <= $famcount) {
				$linefactor = 0;
				// genoffset of 2 needs no adjustment
				if ($genoffset > 2) {
					$tblheight = $bheight + 8;
					if ($genoffset == 3) {
						if ($famcount == 3) {
							$linefactor = $tblheight / 2;
						} else if ($famcount > 3) {
							$linefactor = $tblheight;
						}
					}
					if ($genoffset == 4) {
						if ($famcount == 4) {
							$linefactor = $tblheight;
						} else if ($famcount > 4) {
							$linefactor = ($famcount - $genoffset) * ($tblheight * 1.5);
						}
					}
					if ($genoffset == 5) {
						if ($famcount == 5) {
							$linefactor = 0;
						} else if ($famcount > 5) {
							$linefactor = $tblheight * ($famcount - $genoffset);
						}
					}
				}
				$lh = (($famcount - 1) * ($bheight + 8) - ($linefactor));
				if ($genoffset > 5) {
					$lh = $savlh;
				}
			}
			echo '<img class="line3 pvline"  src="', Theme::theme()->parameter('image-vline'), '" height="', $lh - 1, '" alt=""></td>',
			'<td>',
			'<img class="line4" src="', Theme::theme()->parameter('image-hline'), '" height="3" alt=""></td>',
			'<td>';
			$lh = $savlh; // restore original line height
			//-- print the father box
			print_pedigree_person($family->getHusband());
			echo '</td>';
			if ($family->getHusband()) {
				echo '<td>';
				//-- recursively get the father’s family
				$this->printPersonPedigree($family->getHusband(), $count + 1);
				echo '</td>';
			} else {
				echo '<td>';
				if ($genoffset > $count) {
					echo '<table>';
					for ($i = 1; $i < (pow(2, ($genoffset) - $count) / 2); $i++) {
						$this->printEmptyBox($bwidth, $bheight);
						echo '</tr>';
					}
					echo '</table>';
				}
			}
			echo '</tr><tr>',
			'<td class="tdtop"><img class="pvline" src="', Theme::theme()->parameter('image-vline'), '" height="', $lh + 1, '"></td>',
			'<td><img class="line4" src="', Theme::theme()->parameter('image-hline'), '" height="3"></td>',
			'<td>';
			//-- print the mother box
			print_pedigree_person($family->getWife());
			echo '</td>';
			if ($family->getWife()) {
				echo '<td>';
				//-- recursively print the mother’s family
				$this->printPersonPedigree($family->getWife(), $count + 1);
				echo '</td>';
			} else {
				echo '<td>';
				if ($count < $genoffset - 1) {
					echo '<table>';
					for ($i = 1; $i < (pow(2, ($genoffset - 1) - $count) / 2) + 1; $i++) {
						$this->printEmptyBox($bwidth, $bheight);
						echo '</tr>';
						$this->printEmptyBox($bwidth, $bheight);
						echo '</tr>';
					}
					echo '</table>';
				}
			}
			echo '</tr>',
			'</table>';
			break;
		}
	}

	/**
	 * Calculates number of generations a person has
	 *
	 * @param string  $pid
	 * @param integer $depth
	 *
	 * @return integer
	 */
	private function maxDescendencyGenerations($pid, $depth) {
		if ($depth > $this->generations) {
			return $depth;
		}
		$person = Individual::getInstance($pid);
		if (is_null($person)) {
			return $depth;
		}
		$maxdc = $depth;
		foreach ($person->getSpouseFamilies() as $family) {
			foreach ($family->getChildren() as $child) {
				$dc = $this->maxDescendencyGenerations($child->getXref(), $depth + 1);
				if ($dc >= $this->generations) {
					return $dc;
				}
				if ($dc > $maxdc) {
					$maxdc = $dc;
				}
			}
		}
		$maxdc++;
		if ($maxdc == 1) {
			$maxdc++;
		}

		return $maxdc;
	}

	/**
	 * Print empty box
	 *
	 * @param integer $bwidth
	 * @param integer $bheight
	 */
	private function printEmptyBox($bwidth, $bheight) {
		echo '<tr><td><div style="width:', $bwidth + 16, 'px; height:', $bheight + 8, 'px;"></div></td><td>';
	}

	/**
	 * Print a “Family Book” for an individual
	 *
	 * @param Individual $person
	 * @param integer       $descent_steps
	 */
	public function printFamilyBook(Individual $person, $descent_steps) {
		global $first_run;

		if ($descent_steps == 0 || !$person->canShowName()) {
			return;
		}
		$families = $person->getSpouseFamilies();
		if (count($families) > 0 || empty($first_run)) {
			$first_run = true;
			echo
			'<h3>',
			/* I18N: A title/heading. %s is an individual’s name */ I18N::translate('Family of %s', $person->getFullName()),
			'</h3>',
			'<table class="t0"><tr><td class="tdmid">';
			$this->dgenerations = $this->generations;
			$this->printDescendency($person, 1);
			echo '</td><td class="tdmid">';
			$this->printPersonPedigree($person, 1);
			echo '</td></tr></table><br><br><hr style="page-break-after:always;"><br><br>';
			foreach ($families as $family) {
				foreach ($family->getChildren() as $child) {
					$this->printFamilyBook($child, $descent_steps - 1);
				}
			}
		}
	}
}
