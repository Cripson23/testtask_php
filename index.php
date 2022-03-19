<?php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    setlocale(LC_ALL, 'ru_RU');
    date_default_timezone_set('Europe/Moscow');
    header('Content-type: text/html; charset=utf-8');

    include_once __DIR__ . '/phpQuery.php';

    $parse = new Parsing();

    class Parsing {
        public $numbers = array();
        public $links = array();
        public $ooc_numbers = array();
        public $emails = array();
        public $doc;
        public $db_link;
        public $db_connection;

        function __construct() {
            $this->doc = phpQuery::newDocument(file_get_contents('https://etp.eltox.ru/registry/procedure?&type=1'));
            
            $this->get_numbers_and_links();
            $this->get_ooc_numbers();
            $this->get_emails();
            $this->print_info();
            
            $this->db_link = mysqli_connect('localhost', 'root', '')
                or die('Не удалось соединиться: ' . mysqli_error());

            mysqli_select_db($this->db_link, 'testtask') or die('Не удалось выбрать базу данных');
            echo '<br/><br/>Соединение с базой данных (testtask) успешно установлено';

            $this->write_db();
            $this->print_db();
        }

        function get_numbers_and_links()
        {
            $result = $this->doc->find('.descriptTenderTd dl dt a');

            foreach ($result as $row) {
                $ent = pq($row);

                $number = $ent->text();
                $int_number = preg_replace("/[^0-9]/", '', $number);

                $this->numbers[] = $int_number;

                $url = $ent->attr('href');
                $this->links[] = 'https://etp.eltox.ru' . $url;
            }
        }

        function get_ooc_numbers() {
            $result = $this->doc->find('.descriptTenderTd dl dt span');
        
            foreach ($result as $row) {
                $ent = pq($row);
                $ooc_number = $ent->text();
                $int_ooc_number = preg_replace("/[^0-9]/", '', $ooc_number);

                $this->ooc_numbers[] = $int_ooc_number;
            }
        }

        function get_emails() {
            foreach ($this->links as $link) {
                $doc = phpQuery::newDocument(file_get_contents($link));
                $result = $doc->find('table .table, .detail-view, .table-striped tr');
                
                foreach ($result as $row) {
                    $row = pq($row);
                    $name = $row->find('th:eq(0)')->text();
                    if ($name == 'Почта') {
                        $value = $row->find('td:eq(0)')->text();
                        $this->emails[] = $value;
                    }
                }
            }
        }

        function print_info() {
            print('Номера: <br/>');
            print_r($this->numbers);
            print('<br/><br/>');
            print('Ссылки: <br/>');
            print_r($this->links);
            print('<br/><br/>');
            print('ООС номера: <br/>');
            print_r($this->ooc_numbers);
            print('<br/><br/>');
            print('Почты: <br/>');
            print_r($this->emails);
        }

        function write_db() {
            for ($i = 0; $i < count($this->numbers); $i++) {
                $number = $this->numbers[$i];
                $link = $this->links[$i];
                $ooc_number = $this->ooc_numbers[$i];
                $email = $this->emails[$i];

                $query = "INSERT INTO `parser` (`number`, `ooc_number`, `link`, `email`)
                VALUES ('$number', '$ooc_number', '$link', '$email');";

                $result = mysqli_query($this->db_link, $query) or die('<br/>Запрос не удался: ' . mysqli_error($this->db_link));
            }
        }

        function print_db() {
            $query = 'SELECT * FROM `parser`';
            $result =  mysqli_query($this->db_link, $query) or die('<br/>Запрос не удался: ' . $this->db_link->error);

            print('<br/><br/>Данные в базе:<br/>');
            echo "<table>\n";
            while ($line = mysqli_fetch_assoc($result)) {
                echo "\t<tr>\n";
                foreach ($line as $col_value) {
                    echo "\t\t<td>$col_value</td>\n";
                }
                echo "\t</tr>\n";
            }
            echo "</table>\n";

            mysqli_free_result($result);

            mysqli_close($this->db_link);
        }
    }
?>