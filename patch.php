<?php
/**
 * Patch Script for Atlassian JIRA
 *
 * @author sskaje (http://sskaje.me/)
 */

echo <<<COPYRIGHT
Patch Script for Atlassian JIRA

Author: sskaje (http://sskaje.me/)

COPYRIGHT;

if (!isset($argv[1]) || !is_dir($argv[1])) {
	usage();
}
/**
 * New key
 */
$new_key = "MIIBtjCCASsGByqGSM44BAEwggEeAoGBANKh8wQdkx7LYai+xWpRrMZh+WOFiBfpYM9Qtpk3FiQgYXcoKrthnqscJDqDxplfn8WCZ5PPiywYLm6syjOc01ksZ5ks7p8EtIYtS7WgcyakR13W3d5FOrJOSmJZi/ir8myZv8e8/Ca1hSMBhgwp/ieCn/CUYAOHnKojIg7u/QWVAhUAjlgGxlPM9aSF/oLmWlf3SLCC9BMCgYA+2YENRJ0uKL0hCcWvoFqEjj9Uyns5Z/Nxm71TGfO5jUm5dKGC0MPzq+0E3kTOVOmOZ46YoXwrQIcaEvkbiiqHfCeA/FIXwwQha7Q+92jEqa8qetA0Fz/I4LiZwvkjppmbI/OS3FC3F+W8TYKcXJ28Hadv48JTkAyag0iE6iz7LgOBhAACgYB+ClOtZQYP75QFu/8r+VXJ0I53lBdb+aihhRfQ0Oy4hbe9MklnAzgX09NbN18MjYlBoghmx5oxXTjlYQWuedEoOFWF1xUHQqX8YC9geeR5bdU2ILX6zVgQMhGQvSTWswopKWjrcic1KooA86z6a+k2hPNFc9EYIunbsY61PH4pLw==";
/**
 * Old key hard-coded in Version2LicenseDecoder.class
 */
$old_key = "MIIBuDCCASwGByqGSM44BAEwggEfAoGBAP1/U4EddRIpUt9KnC7s5Of2EbdSPO9EAMMeP4C2USZpRV1AIlH7WT2NWPq/xfW6MPbLm1Vs14E7gB00b/JmYLdrmVClpJ+f6AR7ECLCT7up1/63xhv4O1fnxqimFQ8E+4P208UewwI1VBNaFpEy9nXzrith1yrv8iIDGZ3RSAHHAhUAl2BQjxUjC8yykrmCouuEC/BYHPUCgYEA9+GghdabPd7LvKtcNrhXuXmUr7v6OuqC+VdMCz0HgmdRWVeOutRZT+ZxBxCBgLRJFnEj6EwoFhO3zwkyjMim4TwWeotUfI0o4KOuHiuzpnWRbqN/C/ohNWLx+2J6ASQ7zKTxvqhRkImog9/hWuWfBpKLZl6Ae1UlZAFMO/7PSSoDgYUAAoGBAIvfweZvmGo5otwawI3no7Udanxal3hX2haw962KL/nHQrnC4FG2PvUFf34OecSK1KtHDPQoSQ+DHrfdf6vKUJphw0Kn3gXm4LS8VK/LrY7on/wh2iUobS2XlhuIqEc5mLAUu9Hd+1qxsQkQ50d0lzKrnDqPsM0WA9htkdJJw2nS";

$path = $argv[1];

# File jars
$command = "find '{$path}' -name '*.jar' -exec grep -l Version2LicenseDecoder {} \\;";

$files = array();

$h = popen($command, 'r');
while (!feof($h)) {
	$l = trim(fgets($h));
	if ($l) {
		$files[] = $l;
	}
}
pclose($h);

if (empty($files)) {
	error("No Version2LicenseDecoder found!");
}
# Patch all jars found
foreach ($files as $file) {
	patch($file);
}
/**
 * Patch file
 *
 * @param string $file
 */
function patch($file) {
	echo "Processing file '{$file}'\n";
	
	# is writable?
	if (!is_writable($file) || !is_writable(dirname($file))) {
		error("File not writable!");
	}

	# Backup
	if (!copy($file, $file.".bak.".microtime(1))) {
		error("Failed to backup!");
	}

	# Temporary Folder
	$temp_dir = '/tmp/jira-patch-' . uniqid('', true);
	if (!mkdir($temp_dir)) {
		error("Failed to create {$temp_dir}");
	}

	# Extract files
	$command = "unzip -d '{$temp_dir}' '{$file}' 2>&1 1>/dev/null ";
	system($command);
	echo "Files extracted to {$temp_dir}\n";

	# Find class file
	chdir($temp_dir);
	$command = "cd '{$temp_dir}' && find . -name Version2LicenseDecoder.class";
	$files = array();
	$h = popen($command, 'r');
	while (!feof($h)) {
		$l = trim(fgets($h));
		if ($l) {
			$files[] = $l;
		}
	}
    pclose($h);

	global $old_key, $new_key;
	# Patch & replace
	foreach ($files as $f) {
		# Filename like ./xxxxx
		# Read file & replace
		$content = file_get_contents($f);
		$count = 0;
		$content = str_replace($old_key, $new_key, $content, $count);
		# Check if file is patched
		if (!$count) {
			echo "It seems '{$f}' has already been patched.\n";
			continue;
		}
        # Save patched content
		file_put_contents($f, $content);

		# ./xxxxxx -> xxxxxx
		$f_clean = substr($f, 2);
		# jar -uf
		$command = "cd '{$temp_dir}'; jar uf {$file} {$f_clean}";
		system($command);

		# Clean up
		echo "Removing {$temp_dir}\n";
		system("rm -fr '{$temp_dir}'");

		echo "{$f_clean} updated into {$file}\n\n"; 

	}

	echo "{$file} processed\n\n";
}

/**
 * Usage
 */
function usage() {
        global $argv, $argc;
        echo <<<USAGE
{$argv[0]} PATH_TO_JIRA

USAGE;
        exit;
}

/**
 * Print error and exit
 *
 * @param string $msg
 */
function error($msg) {
	echo "Error: " . trim($msg) . "\n";
	exit; 
}

# EOF