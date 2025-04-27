<?php
$dsn = 'mysql:host=db;dbname=lb_pdo_rent;charset=utf8';
$username = 'user';
$password = 'password';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Помилка підключення до бази даних: " . $e->getMessage());
}

try {
    $logPdo = new PDO('sqlite:./logs.db');
} catch (PDOException $e) {
    die("Помилка підключення до log бази даних: " . $e->getMessage());
}

$logPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function log_request(PDO $logPdo, string $action, array $params = []) {
    $stmt = $logPdo->prepare("INSERT INTO logs (action, parameters) VALUES (:action, :params)");
    $stmt->execute([
        'action' => $action,
        'params' => json_encode($params, JSON_UNESCAPED_UNICODE)
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? date('Y-m-d');
    $vendor = $_POST['vendor'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'income') {
        log_request($logPdo, 'income', ['date' => $date]);

        $query = "SELECT SUM(Cost) AS total_income FROM rent WHERE Date_end <= :date";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['date' => $date]);
        $income = $stmt->fetch()['total_income'] ?? 0;

        // Response in plain text
        echo number_format($income, 2) . " грн";
    } elseif ($action === 'cars_by_vendor') {
        log_request($logPdo, 'cars_by_vendor', ['vendor' => $vendor]);

        $query = "SELECT cars.* FROM cars JOIN vendors ON cars.FID_Vendors = vendors.ID_Vendors WHERE vendors.Name = :vendor";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['vendor' => $vendor]);
        $cars = $stmt->fetchAll();

        // Response in XML
        $xml = new SimpleXMLElement('<root/>');
        foreach ($cars as $car) {
            $carElement = $xml->addChild('car');
            $carElement->addChild('name', $car['Name']);
            $carElement->addChild('release_date', $car['Release_date']);
        }
        header('Content-Type: application/xml');
        echo $xml->asXML();
    } elseif ($action === 'available_cars') {
        log_request($logPdo, 'available_cars', ['date' => $date]);

        $query = "SELECT * FROM cars WHERE ID_Cars NOT IN (SELECT FID_Car FROM rent WHERE :date BETWEEN Date_start AND Date_end)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['date' => $date]);
        $availableCars = $stmt->fetchAll();

        // Response in JSON
        header('Content-Type: application/json');
        echo json_encode($availableCars);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Автопрокат</title>
    <script>
        function fetchData(action, data, callback, responseType) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'index.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function () {
                if (xhr.status === 200) {
                    if (responseType === 'text') {
                        callback(xhr.responseText);
                    } else if (responseType === 'xml') {
                        const xmlDoc = xhr.responseXML;
                        callback(xmlDoc);
                    } else if (responseType === 'json') {
                        const jsonResponse = JSON.parse(xhr.responseText);
                        callback(jsonResponse);
                    }
                }
            };

            let queryString = `action=${action}`;
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    queryString += `&${key}=${encodeURIComponent(data[key])}`;
                }
            }

            xhr.send(queryString);
        }

        function updateIncome() {
            let date = document.getElementById('date').value;
            fetchData('income', { date: date }, function(response) {
                document.getElementById('income').textContent = response + " грн";
            }, 'text');
        }

        function updateCarsByVendor() {
            let vendor = document.getElementById('vendor').value;
            fetchData('cars_by_vendor', { vendor: vendor }, function(xmlResponse) {
                const cars = xmlResponse.getElementsByTagName('car');
                const list = document.getElementById('cars_by_vendor');
                list.innerHTML = '';
                for (let i = 0; i < cars.length; i++) {
                    const name = cars[i].getElementsByTagName('name')[0].textContent;
                    const releaseDate = cars[i].getElementsByTagName('release_date')[0].textContent;
                    list.innerHTML += `<li>${name} (${releaseDate})</li>`;
                }
            }, 'xml');
        }

        function updateAvailableCars() {
            let date = document.getElementById('date').value;
            fetchData('available_cars', { date: date }, function(jsonResponse) {
                const list = document.getElementById('available_cars');
                list.innerHTML = '';
                jsonResponse.forEach(function(car) {
                    list.innerHTML += `<li>${car.Name} (${car.Release_date})</li>`;
                });
            }, 'json');
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('filter-form').addEventListener('submit', function(e) {
                e.preventDefault();
                updateIncome();
                updateCarsByVendor();
                updateAvailableCars();
            });
        });
    </script>
</head>
<body>
    <h1>Інформація про автопрокат</h1>
    <form id="filter-form">
        <label>Оберіть дату: <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>"></label>
        <label>Виробник: <input type="text" id="vendor" name="vendor"></label>
        <button type="submit">Фільтрувати</button>
    </form>

    <h2>Отриманий дохід:</h2>
    <p id="income">0.00 грн</p>

    <h2>Автомобілі виробника:</h2>
    <ul id="cars_by_vendor"></ul>

    <h2>Вільні автомобілі:</h2>
    <ul id="available_cars"></ul>

    <h2>Журнал запитів</h2>
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Дія</th>
            <th>Параметр</th>
            <th>Час</th>
        </tr>

        <?php 
        $logStmt = $logPdo->query("SELECT * FROM logs ORDER BY id DESC");
        $logs = $logStmt->fetchAll();
        ?>


        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['id']) ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><pre><?= htmlspecialchars($log['parameters']) ?></pre></td>
                <td><?= htmlspecialchars($log['timestamp']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
