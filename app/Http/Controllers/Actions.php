<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Cinetpay;
use App\Models\Products;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class Actions extends Controller
{
    private static $apikey = "";
    private static $site_id = 0;
    private static $secret_key = "";

    public function action(Request $request)
    {
        $credentials = $request->validate([
            'name' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|string',
            'number' => 'required|string',
            'id' => 'string|nullable',
            'amount' => 'integer|nullable',
        ]);

        if ($credentials) {
            $customer_name = $request->name;
            $customer_surname = $request->surname;
            $number = $request->number;
            $email = $request->email;
            $amount = $request->amount;
            $description = "Achat de ptoduit";
            $id = $request->id;
            $currency = 'XOF';
        } else {
            return back();
        }

        //transaction id
        $id_transaction = date("YmdHis"); // or $id_transaction = Cinetpay::generateTransId();

        //notify url
        $notify_url = route('notify');

        //return url
        $return_url = route('return', ['produit' => $id]);


        $channels = "ALL";

        /*information supplémentaire que vous voulez afficher
         sur la facture de CinetPay(Supporte trois variables 
         que vous nommez à votre convenance)*/
        $invoice_data = array(
            "Data 1" => "",
            "Data 2" => "",
            "Data 3" => ""
        );

        //
        $formData = array(
            "transaction_id" => $id_transaction,
            "amount" => $amount,
            "currency" => $currency,
            "customer_surname" => $customer_name,
            "customer_name" => $customer_surname,
            "description" => $description,
            "notify_url" => $notify_url,
            "return_url" => $return_url,
            "channels" => $channels,
            "invoice_data" => $invoice_data,
            //pour afficher le paiement par carte de credit
            "customer_email" => $email, //l'email du client
            "customer_phone_number" => $number, //Le numéro de téléphone du client
            "customer_address" => "", //l'adresse du client
            "customer_city" => "Abidjan", // ville du client
            "customer_country" => "CI", //Le pays du client, la valeur à envoyer est le code ISO du pays (code à deux chiffre) ex : CI, BF, US, CA, FR
            "customer_state" => "CI", //L’état dans de la quel se trouve le client. Cette valeur est obligatoire si le client se trouve au États Unis d’Amérique (US) ou au Canada (CA)
            "customer_zip_code" => "" //Le code postal du client 
        );

        // enregistrer la transaction dans votre base de donnée
        $CinetPay = new CinetPay(Actions::$site_id, Actions::$apikey, $VerifySsl = false);
        //$VerifySsl=true <=> Pour activerr la verification ssl sur curl 
        $result = $CinetPay->generatePaymentLink($formData);

        if ($result["code"] == '201') {
            $url = $result["data"]["payment_url"];
            return redirect($url);
        } else {
            return back();
        }
    }

    public function return($id)
    {
        if (isset($_POST['transaction_id']) || isset($_POST['token'])) {
            $id_transaction = $_POST['transaction_id'];
            $token = $_POST['token'];

            // Verification d'etat de transaction chez CinetPay
            $CinetPay = new CinetPay(Actions::$site_id, Actions::$apikey);
            $CinetPay->getPayStatus($id_transaction, Actions::$site_id);
            $message = $CinetPay->chk_message;
            $code = $CinetPay->chk_code;

            //recuperer les info du clients pour personnaliser les reponses.
            /* $commande->getUserByPayment(); */

            // redirection vers une page en fonction de l'état de la transaction

            if ($code == '00') {
                Session::flash('done', 'Felicitation, votre paiement a été effectué avec succès');
                return redirect()->route('detail_produit', [$id]);
            } else {
                Session::flash('fail', 'Echec, votre paiement a échoué');
                return redirect()->route('detail_produit', [$id]);
            }
        } else {
            Session::flash('fail', 'Echec, votre paiement n\'a pas été éffectuer');
            return redirect()->route('detail_produit', [$id]);
        }
    }

    public function notify()
    {
        if (isset($_POST['cpm_trans_id'])) {

            /* Implementer le HMAC pour une vérification supplémentaire .*/
            //Etape 1 : Concatenation des informations posté
            $data_post = implode('', $_POST);

            //Etape 2 : Créer le token suivant la technique HMAC en appliquant l'algorithme SHA256 avec la clé secrète
            $generated_token = hash_hmac('SHA256', $data_post, Actions::$secret_key);

            if ($_SERVER["HTTP_X_TOKEN"]) {
                $xtoken = $_SERVER["HTTP_X_TOKEN"];
            } else {
                return "X-token indisponible";
            }

            //Etape 3: Verifier que le token reçu dans l’en-tête correspond à celui que vous aurez généré.
            if (hash_equals($xtoken, $generated_token)) {
                // Valid Token
                $validtoken = True;

                //Création d'un fichier log pour s'assurer que les éléments sont bien exécuté
                $log  = "User: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL .
                    "TransId:" . $_POST['cpm_trans_id'] . PHP_EOL .
                    "SiteId: " . $_POST['cpm_site_id'] . PHP_EOL .
                    "HMAC RECU: " . $xtoken . PHP_EOL .
                    "HMAC GENERATE: " . $generated_token . PHP_EOL .
                    "VALID-TOKEN: " . $validtoken . PHP_EOL .
                    "-------------------------" . PHP_EOL;

                //file_put_contents('./log_' . date("j.n.Y") . '.log', $log, FILE_APPEND);
                File::put(public_path('/logs/log_' . date("j.n.Y") . '.log'), $log);
                //La classe commande correspond à votre colonne qui gère les transactions dans votre base de données
                // Initialisation de CinetPay et Identification du paiement
                $id_transaction = $_POST['cpm_trans_id'];
                // apiKey
                $apikey = Actions::$apikey;

                // siteId
                $site_id = $_POST['cpm_site_id'];


                $CinetPay = new CinetPay($site_id, $apikey);
                //On recupère le statut de la transaction dans la base de donnée


                // On verifie que la commande n'a pas encore été traité
                $VerifyStatusCmd = "1"; // valeur du statut à recupérer dans votre base de donnée
                if ($VerifyStatusCmd == '00') {
                    // La commande a été déjà traité
                    // Arret du script
                    die();
                }
                // Dans le cas contrait, on verifie l'état de la transaction en cas de tentative de paiement sur CinetPay

                $CinetPay->getPayStatus($id_transaction, $site_id);

                $payment_date = $CinetPay->chk_payment_date;
                $amount = $CinetPay->chk_amount;
                $currency = $CinetPay->chk_currency;
                $message = $CinetPay->chk_message;
                $code = $CinetPay->chk_code;
                $metadata = $CinetPay->chk_metadata;

                //Enregistrement du statut dans le fichier log
                $log  = "User: " . $_SERVER['REMOTE_ADDR'] . ' - ' . date("F j, Y, g:i a") . PHP_EOL .
                    "Code:" . $code . PHP_EOL .
                    "Message: " . $message . PHP_EOL .
                    "Amount: " . $amount . PHP_EOL .
                    "currency: " . $currency . PHP_EOL .
                    "-------------------------" . PHP_EOL;
                //Save string to log, use FILE_APPEND to append.
                //file_put_contents('./log_' . date("j.n.Y") . '.log', $log, FILE_APPEND);
                File::put(public_path('/logs/log_' . date("j.n.Y") . '.log'), $log);

                // On verifie que le montant payé chez CinetPay correspond à notre montant en base de données pour cette transaction
                /*if ($code == '00') {
                    // correct, on delivre le service
                    return 'Felicitation, votre paiement a été effectué avec succès';
                    die();
                } else {
                    // transaction n'est pas valide
                    return 'Echec, votre paiement a échoué pour cause : ' . $message;
                    die();
                }
                // mise à jour des transactions dans la base de donnée
                /*  $commande->update(); */
            } else {
                return "HMAC non-conforme";
            }
        } else {
            // direct acces on IPN
            return "cpm_trans_id non fourni";
        }
    }
}
