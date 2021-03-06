<?php


class PlateformeIOS extends Plateforme
{

    function __construct($apps = null)
    {
        parent::__construct($apps, 'ios', 'iOS');
    }


    public function getDownloadUrlForPath($chemin)
    {
        return 'itms-services://?action=download-manifest&url=' . currentUrl() . '/dl/' . $chemin;
    }


    /**
     * @param $app \Slim\Slim
     * @param $File File
     */
    public function startSpecificDownloadForResource(\Slim\Slim $app, File $File)
    {
        if ($app->request()->get('manifest') === null) {
            $url = 'itms-services://?action=download-manifest&url=' . currentUrl() . '/dl/' . $File->getPath() . '%3Fmanifest';
            $app->response()->body('<html>
			<head>
			<meta name="viewport" content="initial-scale=1, maximum-scale=1">
			<title>Redirection en cours</title>
			</head>
			<body style="text-align: center">
			<h1>redirection en cours</h1>
			<a href="' . $url . '">cliquer ici pour installer l\'application</a><br /><br />
			<a href="' . currentUrl().'/datas/'.$File->getPath() . '">cliquer ici pour télécharger le fichier IPA</a>
			<script type="text/javascript">
				setTimeout(function(){
					window.location.href = "' . $url . '";
				}, 500);
			</script>
			</body>

			</html>');
        } else {

            $plistContent = '';
            $zip = new ZipArchive;
            $res = $zip->open($File->getFullPath());
            if ($res === TRUE) {

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if (preg_match("/^Payload\/(.*)\.app\/Info\.plist$/", $stat['name']) === 1) {
                        $plistContent = $zip->getFromIndex($i);
                        break;
                    }
                }
                $zip->close();
            } else {
                $app->halt(500, 'Impossible de lire le fichier IPA');
                die();
            }

            if (trim($plistContent) == '' || $plistContent === false) {
                $app->halt(500, 'Contenu du pList vide');
                die();
            }

            require_once(DIR . '/vendor/rodneyrehm/plist/classes/CFPropertyList/CFPropertyList.php');
            $plist = new CFPropertyList\CFPropertyList();
            try {
                $plist->parseBinary($plistContent);
            } catch (\CFPropertyList\PListException $e) {
                $plist->parse($plistContent);
            }

            $plistArray = $plist->toArray();

            $app->response->headers->set('Content-Type', 'application/x-plist');
            $app->response->headers->set('Content-disposition', 'attachment; filename="application.plist"');
            $app->render('plateformes/ios.twig',
                array(
                    'plateforme' => $this,
                    'file' => $File,
                    'url' => currentUrl(),
                    'bundleIdentifier' => $plistArray['CFBundleIdentifier'],
                    'bundleVersion' => $plistArray['CFBundleVersion'],
                    'title' => $plistArray['CFBundleName'],
                    'kind' => 'software'
                )
            );

        }
    }
}