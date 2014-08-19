<?php
/**
 * English Alphabet
 *
 * @since 1.0.0
 */
function alphabet_letters() {
	$alphabets = array(
		'a', 
		'b', 
		'c', 
		'd', 
		'e', 
		'f', 
		'g', 
		'h', 
		'i', 
		'j', 
		'k', 
		'l', 
		'm', 
		'n', 
		'o', 
		'p', 
		'q', 
		'r', 
		's', 
		't', 
		'u', 
		'v', 
		'w', 
		'x', 
		'y', 
		'z'
	);
	return $alphabets;
}

function first_letter() {
	return 'a';
}

function last_letter() {
	return 'z';
}

/* This fetches the previous letter. */
function fetch_previous_letter( $letter ) {
	switch ( $letter ) {
		case 'a':
		case 'A':
			$letter = '';
		break;
		case 'b':
		case 'B':
			$letter = 'A';
		break;
		case 'c':
		case 'C':
			$letter = 'B';
		break;
		case 'd':
		case 'D':
			$letter = 'C';
		break;
		case 'e':
		case 'e':
			$letter = 'D';
		break;
		case 'f':
		case 'F':
			$letter = 'E';
		break;
		case 'g':
		case 'G':
			$letter = 'F';
		break;
		case 'h':
		case 'H':
			$letter = 'G';
		break;
		case 'i':
		case 'I':
			$letter = 'H';
		break;
		case 'j':
		case 'J':
			$letter = 'I';
		break;
		case 'k':
		case 'K':
			$letter = 'J';
		break;
		case 'l':
		case 'L':
			$letter = 'K';
		break;
		case 'm':
		case 'M':
			$letter = 'L';
		break;
		case 'n':
		case 'N':
			$letter = 'M';
		break;
		case 'o':
		case 'O':
			$letter = 'N';
		break;
		case 'p':
		case 'P':
			$letter = 'O';
		break;
		case 'q':
		case 'Q':
			$letter = 'P';
		break;
		case 'r':
		case 'R':
			$letter = 'Q';
		break;
		case 's':
		case 'S':
			$letter = 'R';
		break;
		case 't':
		case 'T':
			$letter = 'S';
		break;
		case 'u':
		case 'U':
			$letter = 'T';
		break;
		case 'v':
		case 'V':
			$letter = 'U';
		break;
		case 'w':
		case 'W':
			$letter = 'V';
		break;
		case 'x':
		case 'X':
			$letter = 'W';
		break;
		case 'y':
		case 'Y':
			$letter = 'X';
		break;
		case 'z':
		case 'Z':
			$letter = 'Y';
		break;
	}
	return $letter;
}

?>