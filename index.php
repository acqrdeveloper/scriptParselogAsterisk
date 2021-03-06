<?php

ini_set('memory_limit', '1024M');

define('BASE_PATH', 'queue_rescue_compacted');

//Obtener conexion PDO
function getConnection()
{
    $options = [
        PDO::ATTR_EMULATE_PREPARES => false, // turn off emulation mode for "real" prepared statements
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
    ];
    try{
        $driver = '{MsSQL}';
        $hostname = '';
        $database = '';
        $username = '';
        $password = '';
        //Copiar conexion del .env
        return new PDO("odbc:Driver=$driver;Server=$hostname;Database=$database", $username, $password, $options);
    }catch(PDOException $e){
        return die($e->getMessage());
    }
}

//Select
function select($sql, $params = [])
{
    try{
        $pdo = getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchObject();
    }catch(Exception $e){
        return die($e->getMessage());
    }
}

//Insert
function insert($sql, $params = [])
{
    try{
        $pdo = getConnection();
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }catch(Exception $e){
        return die($e->getMessage());
    }
}

//Validar data del archivo
function validateDataFile()
{
    try{
        $lines = [];
        $path_file = BASE_PATH;
        if(file_exists($path_file)){//Validar si el archivo existe
            $fileOpen = fopen($path_file, "r");
            while(!feof($fileOpen)){//Recorrer el archivo
                $dataFile = fgets($fileOpen);//Archivo fisico
                $lines[] = $dataFile;//Obtener lineas
            }
            fclose($fileOpen);
            $totalLinesFile = count($lines);//Cantidad de lineas del archivo
            $dataCdr = select("SELECT count(id) AS 'totalLinesBD' FROM cdr");//Obtener el total de lineas en la bd
            $newTotalLinesFile = (int)$dataCdr->totalLinesBD + (int)$totalLinesFile;//
            $totalLinesBD = 0;
            if($totalLinesBD < $newTotalLinesFile){
                processDataFile($path_file);
            }
        }else{
            echo 'No existe el archivo en el path: ' . $path_file."\n";
        }
    }catch(Exception $e){
        return die($e->getMessage());
    }
}

//Procesar data del archivo
function processDataFile($path)
{
    try{
        $fileOpen = fopen($path, "r");
        while(!feof($fileOpen)){//Recorrer el archivo
            $dataFile = fgets($fileOpen, 4096);//Archivo fisico
            $dataFile = rtrim($dataFile);//Eliminar espacios vacios
            $lines[] = $dataFile;//Obtener lineas
            $arrayDataFile = explode('|', $dataFile);//Devolver un array por cada linea en el archivo
            $total = count($arrayDataFile) - 1;//5
            while($total < 9){//Recorrer el archivo completando las posiciones que faltan y deben ser 8 en la posicion 0
                $arrayDataFile[$total + 1] = '';
                $total = $total + 1;
            }
            //Crear una lista del arreglo obtenido del archivo
            list ($date, $uniqueid, $qname, $qagent, $qevent, $info1, $info2, $info3, $info4, $info5) = $arrayDataFile;
            $qname_id = selectOrCreateQname($qname);
            $qagent_id = selectOrCreateQagent($qagent);
            $qevent_id = selectOrCreateQevent($qevent);
            $datetime = strftime("%Y-%m-%d %H:%M:%S", $date);
            $resp = insert("insert into queue_stats (uniqueid,datetime,qname,qagent,qevent,info1,info2,info3,info4,info5) values(?,?,?,?,?,?,?,?,?,?)", [$uniqueid, $datetime, $qname_id, $qagent_id, $qevent_id, $info1, $info2, $info3, $info4, $info5]);
            if($resp){
                echo "inserted table queue_stats $uniqueid \n";
            }
        }
        fclose($fileOpen);
    }catch(Exception $e){
        return die($e->getMessage());
    }
}

//Funcion para consultar o crear en la tabla qname
function selectOrCreateQname($qname)
{
    try{
        $sql_select = "SELECT queue_id, queue from dbo.qname WHERE queue = ? ";
        $dataQname = select($sql_select, [$qname]);
        if($dataQname){
            return $dataQname->queue_id;
        }else{
            $sql_insert = "INSERT into dbo.qname (queue) VALUES (?) ";
            insert($sql_insert, [$qname]);
            $dataQname = select($sql_select, [$qname]);
            echo "inserted table qname \n";
            return $dataQname->queue_id;
        }
    }catch(Exception $e){
        return die($e->getMessage());
    }
}

//Funcion para consultar o crear en la tabla qagent
function selectOrCreateQagent($qagent)
{
    try{
        $sql_select = "SELECT agent_id, agent FROM dbo.qagent WHERE agent = ? ";
        $dataQagent = select($sql_select, [$qagent]);
        if($dataQagent){
            return $dataQagent->agent_id;
        }else{
            $sql_insert = "INSERT INTO dbo.qagent (agent) VALUES (?) ";
            insert($sql_insert, [$qagent]);
            $dataQagent = select($sql_select, [$qagent]);
            echo "inserted table qagent \n";
            return $dataQagent->agent_id;
        }
    }catch(Exception $e){
        return die($e->getMessage());
    }
}

//Funcion para consultar o crear en la tabla qevent
function selectOrCreateQevent($qevent)
{
    try{
        $sql_select = "SELECT event_id, event FROM dbo.qevent  WHERE event = ? ";
        $dataQevent = select($sql_select, [$qevent]);
        if($dataQevent){
            return $dataQevent->event_id;
        }else{
            $sql_insert = "INSERT INTO dbo.qevent (event) VALUES (?) ";
            insert($sql_insert, [$qevent]);
            $dataQevent = select($sql_select, [$qevent]);
            echo "inserted table qevent \n";
            return $dataQevent->event_id;
        }
    }catch(Exception $e){
        return die($e->getMessage());
    }
}

//Inicializar funcion
validateDataFile();