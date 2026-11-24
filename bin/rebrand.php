#!/usr/bin/env php
<?php
/**
 * Repeatable Simple Stack rename tool.
 *
 * Reads bin/rebrand-map.json, inventories the legacy naming surface, previews or
 * applies longest-first replacements, and moves tracked paths with git mv.
 */

declare( strict_types=1 );

final class SSTK_Rebrand {

	private string $root;
	private array $map;
	private array $replacements = array();
	private array $files = array();
	private int $changed_files = 0;
	private int $changed_paths = 0;
	private int $replacement_count = 0;

	public function __construct( string $root ) {
		$this->root = rtrim( $root, '/' );
		$map_path = $this->root . '/bin/rebrand-map.json';
		$decoded   = json_decode( (string) file_get_contents( $map_path ), true );
		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( 'Unable to read bin/rebrand-map.json.' );
		}
		$this->map           = $decoded;
		$this->replacements  = $decoded['replacements'] ?? array();
		usort( $this->replacements, static fn( array $a, array $b ): int => strlen( $b['from'] ) <=> strlen( $a['from'] ) );
	}

	public function run( array $argv ): int {
		$options = array_flip( array_slice( $argv, 1 ) );
		if ( isset( $options['--help'] ) ) {
			$this->usage();
			return 0;
		}

		chdir( $this->root );
		$this->files = $this->list_files();

		if ( isset( $options['--inventory'] ) ) {
			$this->write_inventory( isset( $options['--force-inventory'] ) );
			return 0;
		}

		if ( isset( $options['--apply'] ) ) {
			$this->apply();
		} else {
			$this->dry_run();
		}

		return 0;
	}

	private function usage(): void {
		echo "Usage:\n";
		echo "  php bin/rebrand.php --dry-run\n";
		echo "  php bin/rebrand.php --inventory\n";
		echo "  php bin/rebrand.php --inventory --force-inventory\n";
		echo "  php bin/rebrand.php --apply\n";
	}

	private function list_files(): array {
		$output = shell_exec( 'git ls-files -co --exclude-standard -z' );
		if ( null === $output ) {
			throw new RuntimeException( 'Unable to list repository files.' );
		}

		$files = array();
		foreach ( explode( "\0", $output ) as $path ) {
			if ( '' === $path || ! is_file( $path ) || $this->is_excluded( $path ) ) {
				continue;
			}
			$files[] = $path;
		}
		sort( $files );
		return $files;
	}

	private function is_excluded( string $path ): bool {
		if ( in_array( $path, $this->map['include_required_plugin_files'] ?? array(), true ) ) {
			return false;
		}
		foreach ( $this->map['exclude'] ?? array() as $excluded ) {
			if ( str_starts_with( $path, $excluded ) || $path === $excluded ) {
				return true;
			}
		}
		return false;
	}

	private function is_text_file( string $path ): bool {
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( '' === $extension && str_contains( $path, '/scripts/' ) ) {
			return true;
		}
		return in_array( $extension, $this->map['text_extensions'] ?? array(), true );
	}

	private function count_matches( string $text, array $replacement ): int {
		$match = $replacement['match'] ?? 'literal';
		if ( in_array( $match, array( 'word', 'code_word' ), true ) ) {
			$pattern = '/(?<![A-Za-z0-9_])' . preg_quote( $replacement['from'], '/' ) . '(?![A-Za-z0-9_])/';
			return preg_match_all( $pattern, $text );
		}
		return substr_count( $text, $replacement['from'] );
	}

	private function replace_text( string $text, int &$count ): string {
		$count = 0;
		foreach ( $this->replacements as $replacement ) {
			$count += $this->count_matches( $text, $replacement );
			$match = $replacement['match'] ?? 'literal';
			if ( in_array( $match, array( 'word', 'code_word' ), true ) ) {
				$pattern = '/(?<![A-Za-z0-9_])' . preg_quote( $replacement['from'], '/' ) . '(?![A-Za-z0-9_])/';
				$text = preg_replace( $pattern, $replacement['to'], $text ) ?? $text;
			} else {
				$text = str_replace( $replacement['from'], $replacement['to'], $text );
			}
		}
		return $text;
	}

	private function add_modified_header( string $contents, string $path ): string {
		$notice = $this->map['modified_notice'] ?? '';
		if ( '' === $notice || str_contains( $contents, $notice ) ) {
			return $contents;
		}

		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'php' === $extension ) {
			return preg_replace( '/^<\?php\r?\n/', "<?php\n/** $notice */\n", $contents, 1 ) ?? $contents;
		}
		if ( in_array( $extension, array( 'js', 'jsx', 'mjs', 'cjs', 'css', 'scss' ), true ) ) {
			if ( str_starts_with( $contents, '#!' ) ) {
				$newline = strpos( $contents, "\n" );
				if ( false !== $newline ) {
					return substr( $contents, 0, $newline + 1 ) . "/* $notice */\n" . substr( $contents, $newline + 1 );
				}
			}
			return "/* $notice */\n" . $contents;
		}
		if ( 'sh' === $extension || ( '' === $extension && str_contains( $path, '/scripts/' ) ) || ( 'txt' === $extension && str_contains( $path, '/scripts/' ) ) ) {
			if ( str_starts_with( $contents, '#!' ) ) {
				$newline = strpos( $contents, "\n" );
				if ( false !== $newline ) {
					return substr( $contents, 0, $newline + 1 ) . "# $notice\n" . substr( $contents, $newline + 1 );
				}
			}
			return "# $notice\n" . $contents;
		}
		if ( 'xml' === $extension ) {
			return preg_replace( '/^(<\?xml[^>]*\?>\r?\n)/', '$1<!-- ' . $notice . ' -->' . "\n", $contents, 1 ) ?? $contents;
		}
		return $contents;
	}

	private function transform_path( string $path ): string {
		foreach ( $this->map['path_replacements'] ?? array() as $replacement ) {
			if ( $path === $replacement['from'] ) {
				$path = $replacement['to'];
			}
		}
		$ignored = 0;
		return $this->replace_text( $path, $ignored );
	}

	private function read_text( string $path ): ?string {
		if ( ! $this->is_text_file( $path ) ) {
			return null;
		}
		$contents = file_get_contents( $path );
		if ( false === $contents || str_contains( $contents, "\0" ) ) {
			return null;
		}
		return $contents;
	}

	private function dry_run(): void {
		$moves = array();
		foreach ( $this->files as $path ) {
			$target   = $this->transform_path( $path );
			$contents = $this->read_text( $path );
			if ( null !== $contents ) {
				$before   = $contents;
				$count    = 0;
				$contents = $this->replace_text( $contents, $count );
				if ( $contents !== $before || $target !== $path ) {
					$with_header = $this->add_modified_header( $contents, $path );
					if ( $with_header !== $before ) {
						++$this->changed_files;
					}
					$this->replacement_count += $count;
				}
			}
			if ( $target !== $path ) {
				$moves[] = array( $path, $target );
			}
		}

		printf( "Dry run: %d text files would change; %d replacements; %d paths would move.\n", $this->changed_files, $this->replacement_count, count( $moves ) );
		foreach ( array_slice( $moves, 0, 40 ) as $move ) {
			printf( "  %s -> %s\n", $move[0], $move[1] );
		}
		if ( count( $moves ) > 40 ) {
			printf( "  ... %d more path moves\n", count( $moves ) - 40 );
		}
	}

	private function apply(): void {
		$moves        = array();
		$targets      = array();
		$path_targets = array();
		foreach ( $this->files as $path ) {
			$target = $this->transform_path( $path );
			if ( $target === $path ) {
				continue;
			}
			if ( isset( $targets[ $target ] ) ) {
				throw new RuntimeException( "Path collision: {$targets[$target]} and {$path} both map to {$target}." );
			}
			if ( file_exists( $target ) && ! in_array( $target, $this->files, true ) ) {
				throw new RuntimeException( "Path target already exists: {$target}." );
			}
			$targets[ $target ]   = $path;
			$path_targets[ $path ] = $target;
			$moves[]             = array( $path, $target );
		}

		foreach ( $this->files as $path ) {
			$contents = $this->read_text( $path );
			if ( null === $contents ) {
				continue;
			}
			$target   = $path_targets[ $path ] ?? $path;
			$before   = $contents;
			$count    = 0;
			$contents = $this->replace_text( $contents, $count );
			if ( $contents === $before && $target === $path ) {
				continue;
			}
			$contents = $this->add_modified_header( $contents, $path );
			if ( $contents === $before ) {
				continue;
			}
			if ( false === file_put_contents( $path, $contents ) ) {
				throw new RuntimeException( "Unable to write {$path}." );
			}
			++$this->changed_files;
			$this->replacement_count += $count;
		}

		usort( $moves, static fn( array $a, array $b ): int => strlen( $b[0] ) <=> strlen( $a[0] ) );
		foreach ( $moves as $move ) {
			list( $source, $target ) = $move;
			$parent                  = dirname( $target );
			if ( ! is_dir( $parent ) && ! mkdir( $parent, 0777, true ) && ! is_dir( $parent ) ) {
				throw new RuntimeException( "Unable to create {$parent}." );
			}

			exec( 'git ls-files --error-unmatch -- ' . escapeshellarg( $source ) . ' >/dev/null 2>&1', $ignored, $tracked_status );
			if ( 0 === $tracked_status ) {
				exec( 'git mv -- ' . escapeshellarg( $source ) . ' ' . escapeshellarg( $target ), $ignored, $move_status );
			} else {
				$move_status = rename( $source, $target ) ? 0 : 1;
			}
			if ( 0 !== $move_status ) {
				throw new RuntimeException( "Unable to move {$source} to {$target}." );
			}
			++$this->changed_paths;
		}

		$this->remove_empty_legacy_directories( array_column( $moves, 0 ) );
		printf( "Applied: %d text files changed; %d replacements; %d paths moved.\n", $this->changed_files, $this->replacement_count, $this->changed_paths );
	}

	private function remove_empty_legacy_directories( array $paths ): void {
		$directories = array();
		foreach ( $paths as $path ) {
			$directory = dirname( $path );
			while ( '.' !== $directory && '/' !== $directory ) {
				$directories[] = $directory;
				$directory     = dirname( $directory );
			}
		}
		$directories = array_values( array_unique( $directories ) );
		usort( $directories, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );
		foreach ( $directories as $directory ) {
			if ( is_dir( $directory ) ) {
				@rmdir( $directory );
			}
		}
	}

	private function inventory_entries( array &$groups, string $group, string $identifier, string $path ): void {
		if ( ! isset( $groups[ $group ][ $identifier ] ) ) {
			$groups[ $group ][ $identifier ] = array(
				'count' => 0,
				'files' => array(),
			);
		}
		++$groups[ $group ][ $identifier ]['count'];
		$groups[ $group ][ $identifier ]['files'][ $path ] = true;
	}

	private function write_inventory( bool $force = false ): void {
		$inventory_path = $this->root . '/docs/rename-inventory.md';
		if ( ! $force && file_exists( $inventory_path ) ) {
			throw new RuntimeException( 'docs/rename-inventory.md already exists; use --force-inventory to replace it.' );
		}

		$groups = array(
			'classes'      => array(),
			'functions'    => array(),
			'hooks'        => array(),
			'options_meta' => array(),
			'post_types'   => array(),
			'constants'    => array(),
			'rest'         => array(),
			'paths'        => array(),
			'assets'       => array(),
		);
		$summary = array();
		$moves   = array();
		$wpapp   = 0;

		foreach ( $this->replacements as $replacement ) {
			$summary[ $replacement['from'] ] = array(
				'to'    => $replacement['to'],
				'count' => 0,
				'files' => 0,
			);
		}

		foreach ( $this->files as $path ) {
			$target = $this->transform_path( $path );
			if ( $target !== $path ) {
				$moves[] = array( $path, $target );
			}
			$contents = $this->read_text( $path );
			if ( null === $contents ) {
				continue;
			}

			foreach ( $summary as $from => &$entry ) {
				$replacement = array(
					'from' => $from,
					'match' => $this->match_for( $from ),
				);
				$count = $this->count_matches( $contents, $replacement );
				if ( $count > 0 ) {
					$entry['count'] += $count;
					++$entry['files'];
				}
			}
			unset( $entry );

			$wpapp += preg_match_all( '/\b(?:wpapp|WPAPP)\b/', $contents );

			if ( preg_match_all( '/\bclass\s+(WPCD_[A-Za-z0-9_]+|WP_Cloud_Deploy)\b/', $contents, $matches ) ) {
				foreach ( $matches[1] as $identifier ) {
					$this->inventory_entries( $groups, 'classes', $identifier, $path );
				}
			}
			if ( preg_match_all( '/\bfunction\s+(wpcd_[A-Za-z0-9_]+|WPCD)\s*\(/', $contents, $matches ) ) {
				foreach ( $matches[1] as $identifier ) {
					$this->inventory_entries( $groups, 'functions', $identifier, $path );
				}
			}
			if ( preg_match_all( '/\b(?:add_action|add_filter|do_action|apply_filters)\s*\(\s*([\'\"])([^\'\"]+)\1/', $contents, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					if ( preg_match( '/wpcd|WPCD/i', $match[2] ) ) {
						$this->inventory_entries( $groups, 'hooks', $match[2], $path );
					}
				}
			}
			if ( preg_match_all( '/\b(?:get_option|update_option|delete_option|add_option|get_post_meta|update_post_meta|delete_post_meta|get_user_meta|update_user_meta|delete_user_meta)\s*\(\s*([\'\"])([^\'\"]+)\1/', $contents, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					if ( preg_match( '/^wpcd_/', $match[2] ) ) {
						$this->inventory_entries( $groups, 'options_meta', $match[2], $path );
					}
				}
			}
			if ( preg_match_all( '/\bwpcd_(?:app|app_server|team|cloud_provider|command_log|ssh_log|error_log|notify_log|notify_sent_log|pending_tasks_log|permission_type)(?:_[a-z0-9]+)*\b/', $contents, $matches ) ) {
				foreach ( $matches[0] as $identifier ) {
					if ( strlen( $identifier ) <= 20 ) {
						$this->inventory_entries( $groups, 'post_types', $identifier, $path );
					}
				}
			}
			if ( preg_match_all( '/\bdefine\s*\(\s*([\'\"])([^\'\"]+)\1/', $contents, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					if ( preg_match( '/^(?:WPCD_|wpcd_)/', $match[2] ) ) {
						$this->inventory_entries( $groups, 'constants', $match[2], $path );
					}
				}
			}
			if ( preg_match_all( '/wpcd\/v1/', $contents, $matches ) ) {
				foreach ( $matches[0] as $identifier ) {
					$this->inventory_entries( $groups, 'rest', $identifier, $path );
				}
			}
			if ( preg_match_all( '~(?:/etc/wpcd|/var/wpcd)[A-Za-z0-9_./-]*~', $contents, $matches ) ) {
				foreach ( $matches[0] as $identifier ) {
					$this->inventory_entries( $groups, 'paths', $identifier, $path );
				}
			}
			if ( preg_match_all( '/\bwpcd-[a-z0-9-]+\b/', $contents, $matches ) ) {
				foreach ( $matches[0] as $identifier ) {
					$this->inventory_entries( $groups, 'assets', $identifier, $path );
				}
			}
		}

		$lines = array();
		$lines[] = '# Simple Stack rename inventory';
		$lines[] = '';
		$lines[] = 'Generated by `php bin/rebrand.php --inventory` before the hard rename. This file and the rename map are intentional audit artifacts and are excluded from the residual-token gate.';
		$lines[] = '';
		$lines[] = '## Replacement summary';
		$lines[] = '';
		$lines[] = '| Legacy token | Replacement | Occurrences | Files |';
		$lines[] = '|---|---|---:|---:|';
		foreach ( $summary as $from => $entry ) {
			$lines[] = sprintf( '| `%s` | `%s` | %d | %d |', $this->table_escape( $from ), $this->table_escape( $entry['to'] ), $entry['count'], $entry['files'] );
		}
		$lines[] = '';
		$lines[] = sprintf( 'Optional `wpapp` / `WPAPP` identifiers are intentionally unchanged: **%d** occurrences.', $wpapp );
		$lines[] = '';

		$titles = array(
			'classes'      => 'Classes',
			'functions'    => 'Functions',
			'hooks'        => 'Actions and filters',
			'options_meta' => 'Options and meta keys',
			'post_types'   => 'Post types, taxonomies, and other short data keys',
			'constants'    => 'Constants',
			'rest'         => 'REST namespaces',
			'paths'        => 'Server paths',
			'assets'       => 'Asset handles, CSS classes, and IDs',
		);
		foreach ( $titles as $group => $title ) {
			$lines[] = "## $title";
			$lines[] = '';
			if ( empty( $groups[ $group ] ) ) {
				$lines[] = 'None found.';
				$lines[] = '';
				continue;
			}
			$lines[] = '| Identifier | Occurrences | Example files |';
			$lines[] = '|---|---:|---|';
			$keys = array_keys( $groups[ $group ] );
			sort( $keys );
			foreach ( $keys as $identifier ) {
				$entry  = $groups[ $group ][ $identifier ];
				$files  = array_keys( $entry['files'] );
				sort( $files );
				$shown  = array_slice( $files, 0, 5 );
				$suffix = count( $files ) > 5 ? sprintf( ' (+%d more)', count( $files ) - 5 ) : '';
				$lines[] = sprintf( '| `%s` | %d | %s%s |', $this->table_escape( $identifier ), $entry['count'], $this->table_escape( implode( ', ', $shown ) ), $suffix );
			}
			$lines[] = '';
		}

		$lines[] = '## File and directory moves';
		$lines[] = '';
		$lines[] = '| Current path | New path |';
		$lines[] = '|---|---|';
		foreach ( $moves as $move ) {
			$lines[] = sprintf( '| `%s` | `%s` |', $this->table_escape( $move[0] ), $this->table_escape( $move[1] ) );
		}
		$lines[] = '';

		if ( ! is_dir( $this->root . '/docs' ) ) {
			mkdir( $this->root . '/docs', 0777, true );
		}
		if ( false === file_put_contents( $inventory_path, implode( "\n", $lines ) . "\n" ) ) {
			throw new RuntimeException( 'Unable to write docs/rename-inventory.md.' );
		}
		printf( "Wrote docs/rename-inventory.md (%d path moves).\n", count( $moves ) );
	}

	private function match_for( string $from ): string {
		foreach ( $this->replacements as $replacement ) {
			if ( $replacement['from'] === $from ) {
				return $replacement['match'] ?? 'literal';
			}
		}
		return 'literal';
	}

	private function table_escape( string $value ): string {
		return str_replace( array( '|', "\n" ), array( '\\|', ' ' ), $value );
	}
}

try {
	$tool = new SSTK_Rebrand( dirname( __DIR__ ) );
	exit( $tool->run( $argv ) );
} catch ( Throwable $error ) {
	fwrite( STDERR, 'rebrand: ' . $error->getMessage() . "\n" );
	exit( 1 );
}
