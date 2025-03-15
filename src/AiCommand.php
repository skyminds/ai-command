<?php

namespace WP_CLI\AiCommand;

use WP_CLI;
use WP_CLI_Command;

/**
 *
 * Resources: File-like data that can be read by clients (like API responses or file contents)
 * Tools: Functions that can be called by the LLM (with user approval)
 * Prompts: Pre-written templates that help users accomplish specific tasks
 *
 * MCP follows a client-server architecture where:
 *
 * Hosts are LLM applications (like Claude Desktop or IDEs) that initiate connections
 * Clients maintain 1:1 connections with servers, inside the host application
 * Servers provide context, tools, and prompts to clients
 */
class AiCommand extends WP_CLI_Command {
	/**
	 * Greets the world.
	 *
	 * ## OPTIONS
	 *
	 *  <prompt>
	 *  : AI prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Greet the world.
	 *     $ wp ai "What are the titles of my last three posts?"
	 *     Success: Hello World!
	 *
	 *     # Greet the world.
	 *     $ wp ai "create 10 test posts about swiss recipes and include generated featured images"
	 *     Success: Hello World!
	 *
	 * @when after_wp_load
	 *
	 * @param array $args Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$server = new MCP\Server();

		$server->register_tool(
			[
				'name'        => 'calculate_total',
				'description' => 'Calculates the total price.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'price'    => [
							'type'        => 'integer',
							'description' => 'The price of the item.',
						],
						'quantity' => [
							'type'        => 'integer',
							'description' => 'The quantity of items.',
						],
					],
					'required'   => [ 'price', 'quantity' ],
				],
				'callable'    => function ( $params ) {
					$price    = $params['price'] ?? 0;
					$quantity = $params['quantity'] ?? 1;

					return $price * $quantity;
				},
			]
		);

		$server->register_tool(
			[
				'name'        => 'greet',
				'description' => 'Greets the user.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name' => [
							'type'        => 'string',
							'description' => 'The name of the user.',
						],
					],
					'required'   => [ 'name' ],
				],
				'callable'    => function ( $params ) {
					return 'Hello, ' . $params['name'] . '!';
				},
			]
		);

		// Register resources:
		$server->register_resource(
			[
				'name'        => 'users',
				'uri'         => 'data://users',
				'description' => 'List of users',
				'mimeType'    => 'application/json',
				'dataKey'     => 'users', // This tells getResourceData() to look in the $data array
			]
		);

		$server->register_resource(
			[
				'name'        => 'product_catalog',
				'uri'         => 'file://./products.json',
				'description' => 'Product catalog',
				'mimeType'    => 'application/json',
				'filePath'    => './products.json', // This tells getResourceData() to read from a file
			]
		);

		$client = new MCP\Client( $server );

		$server->register_tool(
			[
				'name'        => 'generate_image',
				'description' => 'Generates an image.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'prompt' => [
							'type'        => 'string',
							'description' => 'The prompt for generating the image.',
						],
					],
					'required'   => [ 'prompt' ],
				],
				'callable'    => function ( $params ) use ( $client ) {
					return $client->get_image_from_ai_service( $params['prompt'] );
				},
			]
		);


		$server->register_tool(
			[
				'name'        => 'generate_command',
				'description' => 'Generates a WP CLI command.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'prompt' => [
							'type'        => 'string',
							'description' => 'Takes a prompt to generate a WP CLI command, then run it.',
						],
					],
					'required'   => [ 'prompt' ],
				],
				'callable'    => function ( $params ) use ( $client ) {
					$command = $client->generate_command( $params['prompt'] );
					WP_CLI::success( 'command generated' );
					WP_CLI::success( $command );


					if ( $command == "false" ) {
						return WP_CLI::error( 'No command generated' );
					}

//					if ( str_starts_with( $command, 'wp ' ) ) {
//						$command = substr( $command, 3 );
//					}


//					WP_CLI::log( 'running command' );
//					WP_CLI::runcommand( $command, [] );

				},
			]
		);

		$result = $client->call_ai_service_with_prompt( $args[0] );

//		WP_CLI::success( $result );
	}
}
