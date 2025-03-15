<?php

namespace WP_CLI\AiCommand;

use WP_CLI;
use WP_CLI_Command;

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
        $client = new MCP\Client( $server );

        // Register tools before the user interaction
        $this->register_tools($server, $client);

        // Register resources before the user interaction
        $this->register_resources($server);

        // Call the AI service with the user input prompt
        $result = $client->call_ai_service_with_prompt( $args[0] );

				WP_CLI::success( $result );
    }

    // Register tools for AI processing
    private function register_tools($server, $client) {
			$server->register_tool([
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
					'required'   => ['price', 'quantity'],
				],
				'callable'    => function ($params) {
					return $params['price'] * $params['quantity'];
				},
			]);

			$server->register_tool([
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
					'required'   => ['name'],
				],
				'callable'    => function ($params) {
					return 'Hello, ' . $params['name'] . '!';
				},
			]);

			$server->register_tool([
				'name'        => 'list_posts',
				'description' => 'Retrieves the last N posts.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'count' => [
							'type'        => 'integer',
							'description' => 'The number of posts to retrieve.',
						],
					],
					'required'   => ['count'],
				],
				'callable'    => function ($params) {
					$query = new \WP_Query([
						'posts_per_page' => $params['count'],
						'post_status'    => 'publish',
					]);
					$posts = [];
					while ($query->have_posts()) {
						$query->the_post();
						$posts[] = ['title' => get_the_title(), 'content' => get_the_excerpt()];
					}
					wp_reset_postdata();
					return $posts;
				},
			]);

			$server->register_tool([
				'name'        => 'create_post',
				'description' => 'Creates a new WordPress post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'title'   => [
							'type'        => 'string',
							'description' => 'Title of the post.',
						],
						'content' => [
							'type'        => 'string',
							'description' => 'Content of the post.',
						],
						'post_type' => [
							'type'        => 'string',
							'description' => 'Type of post (e.g., post or page).',
						],
					],
					'required'   => ['title', 'content', 'post_type'],
				],
				'callable'    => function ($params) {
					$post_id = wp_insert_post([
						'post_title'   => $params['title'],
						'post_content' => $params['content'],
						'post_status'  => 'publish',
						'post_type'    => $params['post_type'],
					]);
					return $post_id ? "Post created with ID: $post_id" : 'Error creating post';
				},
			]);

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

			$server->register_tool([
				'name'        => 'create_bulk_posts',
				'description' => 'Creates multiple WordPress posts using AI-generated content.',
				'inputSchema' => [
						'type'       => 'object',
						'properties' => [
								'count' => [
										'type'        => 'integer',
										'description' => 'The number of posts to create.',
								],
								'topics' => [
										'type'        => 'array',
										'description' => 'An array of topics to generate posts for (optional).',
										'items'       => [
												'type' => 'string',
										],
								],
						],
						'required'   => ['count'],
				],
				'callable'    => function ($params) {
						// If no topics are provided, generate random topics
						$topics = isset($params['topics']) && !empty($params['topics']) ? $params['topics'] : $this->generate_random_topics();

						$created_posts = [];
						for ($i = 0; $i < $params['count']; $i++) {
								$topic = $topics[array_rand($topics)]; // Pick a random topic from the list

								// Generate post title and content using AI
								$title = $this->generate_ai_content("Generate a post title about $topic.");
								$content = $this->generate_ai_content("Write a detailed post about $topic.");

								// Create the post using the create_post method
								$post_id = wp_insert_post([
										'post_title'   => $title,
										'post_content' => $content,
										'post_status'  => 'publish',
										'post_type'    => 'post',
								]);

								if ($post_id) {
										$created_posts[] = "Post created with ID: $post_id, Title: $title";
								}
						}

						return $created_posts;
				},
			]);
    }

    // Register resources for AI access
    private function register_resources($server) {
        // Register Users resource
        $server->register_resource([
            'name'        => 'users',
            'uri'         => 'data://users',
            'description' => 'List of users',
            'mimeType'    => 'application/json',
            'dataKey'     => 'users', // Data will be fetched from 'users'
        ]);

        // Register Product Catalog resource
        $server->register_resource([
            'name'        => 'product_catalog',
            'uri'         => 'file://./products.json',
            'description' => 'Product catalog',
            'mimeType'    => 'application/json',
            'filePath'    => './products.json', // Data will be fetched from products.json
        ]);
    }

}
