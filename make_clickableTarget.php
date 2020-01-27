<?php 
function make_clickableTarget($text, $target)
{
	if ($target == true && !empty($target) && $target != null) {
		$target == true;
	} else {
		$target == false;
	}
	$r               = '';
	$textarr         = preg_split('/(<[^<>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE); // split out HTML tags
	$nested_code_pre = 0; // Keep track of how many levels link is nested inside <pre> or <code>
	foreach ($textarr as $piece) {

		if (preg_match('|^<code[\s>]|i', $piece) || preg_match('|^<pre[\s>]|i', $piece) || preg_match('|^<script[\s>]|i', $piece) || preg_match('|^<style[\s>]|i', $piece)) {
			$nested_code_pre++;
		} elseif ($nested_code_pre && ('</code>' === strtolower($piece) || '</pre>' === strtolower($piece) || '</script>' === strtolower($piece) || '</style>' === strtolower($piece))) {
			$nested_code_pre--;
		}

		if ($nested_code_pre || empty($piece) || ($piece[0] === '<' && !preg_match('|^<\s*[\w]{1,20}+://|', $piece))) {
			$r .= $piece;
			continue;
		}

		// Long strings might contain expensive edge cases ...
		if (10000 < strlen($piece)) {
			// ... break it up
			foreach (_split_str_by_whitespace($piece, 2100) as $chunk) { // 2100: Extra room for scheme and leading and trailing paretheses
				if (2101 < strlen($chunk)) {
					$r .= $chunk; // Too big, no whitespace: bail.
				} else {
					$r .= make_clickable($chunk);
				}
			}
		} else {
			$ret = " $piece "; // Pad with whitespace to simplify the regexes

			$url_clickable = '~
                ([\\s(<.,;:!?])                                        # 1: Leading whitespace, or punctuation
                (                                                      # 2: URL
                    [\\w]{1,20}+://                                # Scheme and hier-part prefix
                    (?=\S{1,2000}\s)                               # Limit to URLs less than about 2000 characters long
                    [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+         # Non-punctuation URL character
                    (?:                                            # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
                        [\'.,;:!?)]                            # Punctuation URL character
                        [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
                    )*
                )
                (\)?)                                                  # 3: Trailing closing parenthesis (for parethesis balancing post processing)
            ~xS';
			// The regex is a non-anchored pattern and does not have a single fixed starting character.
			// Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.

			$ret = preg_replace_callback($url_clickable, '_make_url_clickable_cb', $ret);

			$ret = preg_replace_callback('#([\s>])((www|ftp)\.[\w\\x80-\\xff\#$%&~/.\-;:=,?@\[\]+]+)#is', '_make_web_ftp_clickable_cb', $ret);
			$ret = preg_replace_callback('#([\s>])([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})#i', '_make_email_clickable_cb', $ret);

			$ret = substr($ret, 1, -1); // Remove our whitespace padding.
			$r  .= $ret;
		}
	}

	// Cleanup of accidental links within links
	$r =  preg_replace('#(<a([ \r\n\t]+[^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i', '$1$3</a>', $r);
	if ($target == true) {
		$r = str_replace('">', '" target="_blank">', $r);
	}
	return $r;
}