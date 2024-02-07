<?php

declare(strict_types=1);

// create pdo for postgres
$pdo = new \PDO('pgsql:host=db;port=5432;dbname=rinhadb', 'root', '1234');

do {
	$running = \frankenphp_handle_request(function () use ($pdo) {

		$route = explode('/', $_SERVER['REQUEST_URI']);

		if (!isset($route[1]) || $route[1] !== 'clientes') {
			headers_send(404);
			echo 'Invalid request 1';
			return;
		}

		if (!isset($route[2]) || !isset($route[3])) {
			headers_send(404);
			echo 'Invalid request 2';
			return;
		}

		$clientId = (int) $route[2];

		switch ($route[3]) {
			case 'transacoes':
				break;
			case 'extrato':
				break;
			default:
				http_response_code(400);
				echo 'Invalid request 3';
		}

		$data = json_decode(file_get_contents('php://input'), true);

		// test pdo with a simple query

	});

	gc_collect_cycles();
} while ($running);
