<?php
/**
 * Require an ABSPATH direct-access guard in shipped plugin PHP.
 *
 * @package updatebee
 */

declare(strict_types=1);

namespace UpdateBee\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Report shipped PHP files that can be requested directly and do not exit first.
 *
 * @implements Rule<FileNode>
 */
class RequireAbspathGuardRule implements Rule {

	/**
	 * Path prefixes, relative to the plugin root, that Grunt puts in the zip.
	 *
	 * vendor/ is shipped for production autoload but is generated and guarded
	 * separately. bin/ and tests/ are not in the zip.
	 *
	 * @var list<string>
	 */
	private const SHIPPED_PREFIXES = array(
		'includes/',
		'assets/',
		'languages/',
		'build/',
	);

	/**
	 * Directories that are never first-party shipped source for this check.
	 *
	 * @var list<string>
	 */
	private const EXCLUDED_SEGMENTS = array(
		'vendor',
		'node_modules',
		'tests',
		'bin',
		'phpstan',
	);

	public function getNodeType(): string {
		return FileNode::class;
	}

	/**
	 * @param FileNode $node
	 * @return list<IdentifierRuleError>
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$file = $scope->getFile();
		if ( ! $this->is_shipped_first_party_php( $file ) ) {
			return array();
		}

		$nodes          = $node->getNodes();
		$has_namespace  = $this->file_has_namespace( $nodes );
		$past_namespace = false;
		$guard_above    = false;
		$guard_inside   = false;
		$saw_code       = false;
		$guard_line     = null;
		$namespace_line = null;

		foreach ( $this->top_level_statements( $nodes ) as $item ) {
			if ( $item['namespace'] ) {
				$past_namespace = true;
				if ( null === $namespace_line ) {
					$namespace_line = $item['node']->getStartLine();
				}
				continue;
			}

			$statement = $item['node'];
			if ( $this->is_non_executable_prelude( $statement ) ) {
				continue;
			}

			if ( $this->is_direct_access_guard( $statement ) ) {
				if ( $has_namespace && ! $past_namespace ) {
					$guard_above = true;
					$guard_line  = $statement->getStartLine();
					continue;
				}

				if ( ! $saw_code ) {
					return array();
				}

				$guard_inside = true;
				continue;
			}

			if ( $has_namespace && ! $past_namespace ) {
				continue;
			}

			$saw_code = true;
		}

		if ( $has_namespace && $guard_above && ! $guard_inside ) {
			return array(
				RuleErrorBuilder::message(
					'ABSPATH direct-access guard must be beneath the namespace declaration.'
				)
					->identifier( 'updatebee.abspathGuardAboveNamespace' )
					->line( $guard_line ?? $namespace_line ?? 1 )
					->tip( 'Move the ABSPATH check to after the namespace declaration, before any other executable code. A guard above the namespace does not satisfy this rule.' )
					->build(),
			);
		}

		if ( $has_namespace ) {
			return array(
				RuleErrorBuilder::message(
					'File is missing an ABSPATH direct-access guard beneath the namespace declaration.'
				)
					->identifier( 'updatebee.missingAbspathGuard' )
					->line( $namespace_line ?? 1 )
					->tip( 'After the namespace declaration, add if ( ! defined( \'ABSPATH\' ) ) { exit; } or die(); before any other executable code.' )
					->build(),
			);
		}

		return array(
			RuleErrorBuilder::message(
				'File is missing an ABSPATH direct-access guard before executable code.'
			)
				->identifier( 'updatebee.missingAbspathGuard' )
				->line( 1 )
				->tip( 'Add defined( \'ABSPATH\' ) || exit; or if ( ! defined( \'ABSPATH\' ) ) { exit; } before any other executable code.' )
				->build(),
		);
	}

	/**
	 * Whether this file is first-party PHP that the release zip can expose.
	 */
	private function is_shipped_first_party_php( string $file ): bool {
		$relative = $this->relative_plugin_path( $file );
		if ( null === $relative || ! str_ends_with( $relative, '.php' ) ) {
			return false;
		}

		$segments = explode( '/', $relative );
		foreach ( $segments as $segment ) {
			if ( in_array( $segment, self::EXCLUDED_SEGMENTS, true ) ) {
				return false;
			}
		}

		if ( 'updatebee.php' === $relative ) {
			return true;
		}

		foreach ( self::SHIPPED_PREFIXES as $prefix ) {
			if ( str_starts_with( $relative, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Plugin-relative path, or null when the file is outside this plugin.
	 */
	private function relative_plugin_path( string $file ): ?string {
		$path = str_replace( '\\', '/', $file );
		$real = realpath( $path );
		if ( is_string( $real ) ) {
			$path = str_replace( '\\', '/', $real );
		}

		$directory = $path;
		$previous  = null;
		while ( $directory !== $previous ) {
			if ( is_file( $directory . '/updatebee.php' ) && is_file( $directory . '/phpstan.neon.dist' ) ) {
				$root = rtrim( str_replace( '\\', '/', $directory ), '/' );
				if ( $path === $root ) {
					return null;
				}
				if ( ! str_starts_with( $path, $root . '/' ) ) {
					return null;
				}
				return substr( $path, strlen( $root ) + 1 );
			}

			$previous  = $directory;
			$directory = dirname( $directory );
		}

		return null;
	}

	/**
	 * @param Node[] $nodes
	 */
	private function file_has_namespace( array $nodes ): bool {
		foreach ( $nodes as $node ) {
			if ( $node instanceof Namespace_ ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Walk file statements in execution order, marking where a namespace starts.
	 *
	 * Statements that belong to a namespace are visited after that namespace.
	 *
	 * @param Node[] $nodes
	 * @return list<array{namespace: bool, node: Node}>
	 */
	private function top_level_statements( array $nodes ): array {
		$items = array();

		foreach ( $nodes as $node ) {
			if ( $node instanceof Namespace_ ) {
				$items[] = array(
					'namespace' => true,
					'node'      => $node,
				);
				foreach ( $node->stmts ?? array() as $statement ) {
					$items[] = array(
						'namespace' => false,
						'node'      => $statement,
					);
				}
				continue;
			}

			$items[] = array(
				'namespace' => false,
				'node'      => $node,
			);
		}

		return $items;
	}

	/**
	 * declare(), imports, and empty statements do not run on a direct request.
	 */
	private function is_non_executable_prelude( Node $node ): bool {
		return $node instanceof Declare_
			|| $node instanceof Nop
			|| $node instanceof Use_
			|| $node instanceof GroupUse;
	}

	/**
	 * True for the direct-access guards already used in this plugin.
	 *
	 * Comments are not statements, so a comment that only mentions ABSPATH does not match.
	 */
	private function is_direct_access_guard( Node $node ): bool {
		if ( $node instanceof If_ ) {
			if ( ! $this->is_not_defined_abspath( $node->cond ) ) {
				return false;
			}

			foreach ( $node->stmts as $statement ) {
				if ( $statement instanceof Expression && $statement->expr instanceof Exit_ ) {
					return true;
				}
			}

			return false;
		}

		if ( ! $node instanceof Expression ) {
			return false;
		}

		$expr = $node->expr;
		if ( ! $expr instanceof BooleanOr && ! $expr instanceof LogicalOr ) {
			return false;
		}

		return $this->is_defined_abspath_call( $expr->left ) && $expr->right instanceof Exit_;
	}

	private function is_not_defined_abspath( Node $node ): bool {
		return $node instanceof BooleanNot && $this->is_defined_abspath_call( $node->expr );
	}

	private function is_defined_abspath_call( Node $node ): bool {
		if ( ! $node instanceof FuncCall || ! $node->name instanceof Name ) {
			return false;
		}

		if ( 'defined' !== $node->name->toLowerString() ) {
			return false;
		}

		if ( 1 !== count( $node->args ) ) {
			return false;
		}

		$argument = $node->args[0];
		if ( ! $argument instanceof Arg || ! $argument->value instanceof String_ ) {
			return false;
		}

		return 'ABSPATH' === $argument->value->value;
	}
}
