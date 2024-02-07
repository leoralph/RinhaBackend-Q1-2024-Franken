<?php

declare(strict_types=1);

// create pdo for postgres
$pdo = new \PDO('pgsql:host=db;port=5432;dbname=rinhadb', 'root', '1234');

do {
	$running = \frankenphp_handle_request(function () use ($pdo) {
		header('Content-Type: application/json');

		$route = explode('/', $_SERVER['REQUEST_URI']);

		if (!isset($route[1]) || $route[1] !== 'clientes') {
			return headers_send(404);
		}

		if (!isset($route[2]) || !isset($route[3]) || !is_numeric($route[2])) {
			return headers_send(404);
		}

		$clientId = (int) $route[2];

		switch ($route[3]) {
			case 'transacoes':
				if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
					return http_response_code(405);
				}

				$data = json_decode(file_get_contents('php://input'), true);

				if (
					!isset($data['valor'])
					|| !isset($data['tipo'])
					|| !isset($data['descricao'])
				) {
					return http_response_code(422);
				}

				if (
					!is_int($data['valor'])
					|| !in_array($data['tipo'], ['c', 'd'])
					|| !is_string($data['descricao'])
					|| strlen($data['descricao']) < 1
					|| strlen($data['descricao']) > 10
				) {
					return http_response_code(422);
				}

				$pdo->beginTransaction();

				$client = $pdo->query("SELECT limite, saldo FROM clients WHERE id = $clientId FOR UPDATE")->fetchObject();

				if (!$client) {
					$pdo->rollBack();
					return http_response_code(404);
				}

				if ($data['tipo'] === 'd' && $client->saldo - $data['valor'] < 0) {
					$pdo->rollBack();
					return http_response_code(422);
				}

				$newBalance = $data['tipo'] === 'c'
					? $client->saldo + $data['valor']
					: $client->saldo - $data['valor'];

				$pdo->exec(
					"UPDATE clients SET saldo = $newBalance WHERE id = $clientId"
				);

				$now = date(\DateTime::ISO8601);

				$pdo->exec(
					"INSERT INTO transactions (client_id, valor, tipo, descricao, realizada_em)
					VALUES ($clientId, {$data['valor']}, '{$data['tipo']}', '{$data['descricao']}', '{$now}')"
				);

				$pdo->commit();

				echo json_encode([
					"saldo" => $newBalance,
					"limite" => $client->limite,
				]);

				break;
			case 'extrato':
				if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
					return http_response_code(405);
				}

				$client = $pdo->query("SELECT limite, saldo FROM clients WHERE id = $clientId")->fetchObject();

				if (!$client) {
					return http_response_code(404);
				}

				$lastTransactions = $pdo->query(
					"SELECT
						valor, tipo, descricao, realizada_em
					FROM transactions WHERE client_id = $clientId
					ORDER BY id DESC LIMIT 10"
				)->fetchAll(PDO::FETCH_OBJ);

				echo json_encode([
					"saldo" => [
						"total" => $client->saldo,
						"data_extrato" => date(\DateTime::ISO8601),
						"limite" => $client->limite,
					],
					"ultimas_transacoes" => $lastTransactions
				]);

				return;

			default:
				return http_response_code(404);
		}

	});

	gc_collect_cycles();
} while ($running);
