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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? date('Y-m-d');
    $vendor = $_POST['vendor'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'income') {
        $query = "SELECT SUM(Cost) AS total_income FROM rent WHERE Date_end <= :date";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['date' => $date]);
        echo json_encode(['income' => $stmt->fetch()['total_income'] ?? 0]);
    } elseif ($action === 'cars_by_vendor') {
        $query = "SELECT cars.* FROM cars JOIN vendors ON cars.FID_Vendors = vendors.ID_Vendors WHERE vendors.Name = :vendor";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['vendor' => $vendor]);
        echo json_encode($stmt->fetchAll());
    } elseif ($action === 'available_cars') {
        $query = "SELECT * FROM cars WHERE ID_Cars NOT IN (SELECT FID_Car FROM rent WHERE :date BETWEEN Date_start AND Date_end)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['date' => $date]);
        echo json_encode($stmt->fetchAll());
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function fetchData(action, data, callback) {
            $.post("index.php", { action: action, ...data }, function(response) {
                callback(JSON.parse(response));
            });
        }

        function updateIncome() {
            let date = $('#date').val();
            fetchData('income', { date: date }, function(data) {
                $('#income').text(data.income.toFixed(2) + ' грн');
            });
        }

        function updateCarsByVendor() {
            let vendor = $('#vendor').val();
            fetchData('cars_by_vendor', { vendor: vendor }, function(data) {
                let list = $('#cars_by_vendor');
                list.empty();
                data.forEach(car => {
                    list.append(`<li>${car.Name} (${car.Release_date})</li>`);
                });
            });
        }

        function updateAvailableCars() {
            let date = $('#date').val();
            fetchData('available_cars', { date: date }, function(data) {
                let list = $('#available_cars');
                list.empty();
                data.forEach(car => {
                    list.append(`<li>${car.Name} (${car.Release_date})</li>`);
                });
            });
        }

        $(document).ready(function() {
            $('#filter-form').on('submit', function(e) {
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
</body>
</html>
