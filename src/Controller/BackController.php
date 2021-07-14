<?php

namespace App\Controller;

use App\Entity\Achat;
use App\Entity\Article;
use App\Entity\Categorie;
use App\Entity\Commande;
use App\Form\ArticleType;
use App\Form\CategorieType;
use App\Repository\ArticleRepository;
use App\Repository\CategorieRepository;
use App\Repository\CommandeRepository;
use App\Repository\UserRepository;
use App\Service\Panier\PanierService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class BackController extends AbstractController
{
    /**
     * @Route("/addArticle", name="addArticle")
     */
    public function addArticle(Request $request, EntityManagerInterface $manager)
    {

        $article = new Article();// Ici on instancie un nouvel objet Article vide que l'on va charger avec les données du formulaire

        $form = $this->createForm(ArticleType::class, $article, array('ajout'=>true));// Ici on instancie un objet form qui va controler automatiquement la correspondance des champs de formulaire (contenus dans articleType) avec l'entité Article (contenu dans $article).

        $form->handleRequest($request); // la  methode handleRequest() de Form nous permet de preparer la requete et remplir notre Objet Article instancié

        if ($form->isSubmitted() && $form->isValid()): // si le formulaire a ete soumis et qu'il est valide (boolean de correspondance genere dans le createForm)
            $article->setCreateAt(new \DateTime('now'));
            $photo = $form->get('photo')->getData();// on recupere l'input type file photo de notre formulaire, grace a getData() on obtient $_FILE dans son intégralité
            if ($photo):
                $nomphoto = date('YmdHis').uniqid().$photo->getClientOriginalName(); // Ici on modifie le nom de notre photo avec uniqid(), fonction de php generant une cle de hashage de 10 caractere aleatoires concatene avec son nom et la date avec heure, minute et seconde pour s'assurer de l'unité de la photo en bdd et en upload
                $photo->move(
                    $this->getParameter('upload_directory'),
                    $nomphoto
                ); //equivalent du move_uploaded_file() en symfony attendant 2 parametres, la direction de l'upload (defini dans config/service.yaml dans les parameters et le nom du fichier à inserer)
                $article->setPhoto($nomphoto);

                $manager->persist($article); //le manager de symfony fait le lien entre l'entité et la BDD vie l'ORM (Object Relationnel MApping) Doctrine. Grace a la methode persist(), il conserve en memoire la requete preparée.
                $manager->flush(); // ici la methode flush() execute les requete en memoire

                $this->addFlash('success', 'L\'article à bien été ajouté');
                return $this->redirectToRoute('ListeArticle');
            endif;

        endif;

        return $this->render('back/addArticle.html.twig',[
            'form'=>$form->createView(),
            'article'=>$article
        ]);
    }


    /**
     * @Route("/ListeArticle", name="ListeArticle")
     */
    public function listeArticle(ArticleRepository $articleRepository)
    {
        $articles=$articleRepository->findAll();

        return $this->render('back/listeArticle.html.twig',[
            'articles'=>$articles
        ]);
    }

    /**
     * @Route("/modifArticle/{id}", name="modifArticle")
     */
    public function modifArticle(Article $article, Request $request, EntityManagerInterface $manager)
    {
        // lorsqu'un id est transité dans l'URL et une entité est injecté en dependance, symfony instancie automatiquement l'objet entité et le rempli avec ses données en BDD. Pas besoin d'utiliser la méthode Find($id) du repository

        $form=$this->createForm(ArticleType::class, $article);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()):
            $photo = $form->get('photoModif')->getData();

            if ($photo):
                $nomphoto = date('YmdHis').uniqid().$photo->getClientOriginalName();

                $photo->move(
                    $this->getParameter('upload_directory'),
                    $nomphoto
                );

                unlink($this->getParameter('upload_directory').'/'.$article->getPhoto());

                $article->setPhoto($nomphoto);


            endif;
            $manager->persist($article);
            $manager->flush();

            $this->addFlash('success', 'L\'article à bien été modifié');
            return $this->redirectToRoute('ListeArticle');
        endif;

        return $this->render('back/modifArticle.html.twig',[
            'form'=>$form->createView(),
            'article'=>$article
        ]);
    }


    /**
     * @Route("/deleteArticle/{id}", name="deleteArticle")
     */
    public function deleteArticle(Article $article, EntityManagerInterface $manager)
    {
        $manager->remove($article);
        $manager->flush();
        $this->addFlash('success', 'L\'article à bien été supprimé');
        return $this->redirectToRoute('ListeArticle');
    }


    /**
     * @Route("/ajoutCategorie", name="ajoutCategorie")
     * @Route("/modifCategorie/{id}", name="modifCategorie")
     */
    public function categorie(Categorie $categorie=null, EntityManagerInterface $manager, Request $request)
    {
        if(!$categorie):
            $categorie = new Categorie();
        endif;

        $form=$this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()):
            $manager->persist(($categorie));
            $manager->flush();
            $this->addFlash('success', 'La catégorie a bien été créée');

            return $this->redirectToRoute('listeCategorie');
        endif;


        return $this->render("back/categorie.html.twig",[
            'form'=>$form->createView()
        ]);
    }

    /**
     * @Route("/listeCategorie", name="listeCategorie")
     */
    public function listeCategorie(CategorieRepository $categorieRepository)
    {
        $categories=$categorieRepository->findAll();

        return $this->render('back/listeCategorie.html.twig',[
            'categories'=>$categories
        ]);
    }

    /**
     * @Route("/deleteCategorie/{id}", name="deleteCategorie")
     */
    public function deleteCategorie(Categorie $categorie, EntityManagerInterface $manager)
    {
        $manager->remove($categorie);
        $manager->flush();
        $this->addFlash('success', 'La catégorie à bien été supprimé');
        return $this->redirectToRoute('listeCategorie');
    }

    /**
     * @Route("/addPanier/{id}", name="addPanier")
     */
    public function addPanier($id, PanierService $panierService)
    {

        $panierService->add($id);
        $panier = $panierService->getFullPanier();
        $total = $panierService->getTotal();

        return $this->redirectToRoute('home', [
            'panier'=>$panier,
            'total'=>$total
        ]);
    }

    /**
     * @Route("/add/{id}", name="add")
     */
    public function add($id, PanierService $panierService)
    {

        $panierService->add($id);
        $panier = $panierService->getFullPanier();
        $total = $panierService->getTotal();

        return $this->redirectToRoute('panier', [
            'panier'=>$panier,
            'total'=>$total
        ]);
    }

    /**
     * @Route("/remove/{id}", name="remove")
     */
    public function remove($id, PanierService $panierService)
    {

        $panierService->remove($id);
        $panier = $panierService->getFullPanier();
        $total = $panierService->getTotal();

        return $this->redirectToRoute('panier', [
            'panier'=>$panier,
            'total'=>$total
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     */
    public function delete($id, PanierService $panierService)
    {

        $panierService->delete($id);
        $panier = $panierService->getFullPanier();
        $total = $panierService->getTotal();

        return $this->redirectToRoute('panier', [
            'panier'=>$panier,
            'total'=>$total
        ]);
    }

    /**
     * @Route("/deletePanier", name="deletePanier")
     */
    public function deletePanier(PanierService $panierService)
    {

        $panierService->deleteAll();

        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/commande", name="commande")
     */
    public function commande(PanierService $panierService, SessionInterface $session, EntityManagerInterface $manager)
    {
        $panier=$panierService->getFullPanier();

        $commande= new Commande();
        $commande->setMontantTotal($panierService->getTotal());
        $commande->setUser($this->getUser());
        $commande->setStatut(0);
        $commande->setDate(new \DateTime());

        foreach ($panier as $item):
            $article = $item['article'];
            $achat= new Achat();
            $achat->setArticle($article);
            $achat->setQuantite($item['quantite']);
            $achat->setCommande($commande);

            $manager->persist($achat);

        endforeach;

        $manager->persist($commande);
        $manager->flush();

        $panierService->deleteAll();

        $this->addFlash('success','Votre commande a été prise en compte');

        return $this->redirectToRoute('listeCommande');
    }

    /**
     * @Route("/listeCommande", name="listeCommande")
     */
    public function listeCommande(CommandeRepository $commandeRepository)
    {
        $commandes= $commandeRepository->findBy(['user'=>$this->getUser()]);

        return $this->render('front\listeCommande.html.twig', [
            'commandes'=>$commandes
        ]);
    }

    /**
     * @Route("/gestionCommande", name="gestionCommande")
     */
    public function gestionCommande(CommandeRepository $commandeRepository)
    {
        $commandes= $commandeRepository->findBy([], ['statut'=>'ASC']);

        return $this->render('back\gestionCommande.html.twig', [
            'commandes'=>$commandes
        ]);
    }

    /**
     * @Route("/statut/{id}/{param}", name="statut")
     */
    public function statut(CommandeRepository $commandeRepository, EntityManagerInterface $manager, $id, $param)
    {
        $commande = $commandeRepository->find($id);

        $commande->setStatut($param);
        $manager->persist($commande);
        $manager->flush();
         return $this->redirectToRoute('gestionCommande');
    }

    /**
     * @Route ("/sendMail", name="sendMail")
     */
    public function sendMail(Request $request)
    {
        $transporter=(new \Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
        ->setUsername('test.testwf3@gmail.com')
        ->setPassword('testwf3dev');

        $mailer=new \Swift_Mailer($transporter);

        $mess = $request->request->get('message');
        $name = $request->request->get('name');
        $surname = $request->request->get('surname');
        $subject = $request->request->get('need');
        $from = $request->request->get('email');

        $message= (new \Swift_Message($subject))
            ->setFrom($from)
            ->setTo('test.testwf3@gmail.com');

        $cid=$message->embed(\Swift_Image::fromPath("upload/logo.png"));
        $message->SetBody(
            $this->render('mail/mail_template.html.twig', [
                'from'=>$from,
                'name'=>$name,
                'surname'=>$surname,
                'subject'=>$subject,
                'message'=>$mess,
                'logo'=>$cid,
                'objectif'=>'Accéder au site',
                'liens'=> 'http://127.0.0.1:8000'
            ]),
            'text/html'
        );
        $mailer->send($message);

        $this->addFlash('success', 'l\'email a bien été transmis');
        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/mailForm", name="mailForm")
     */
    public function mailForm()
    {
        return $this->render('mail/mail_form.html.twig');
    }

    /**
     * @Route("/mailTemplate", name="mailTemplate")
     */
    public function mailTemplate()
    {
        return $this->render('mail/mail_template.html.twig');
    }


    /**
     * @Route("/forgotPassword", name="forgotPassword")
     */
    public function forgotPassword(Request $request, UserRepository $repository, EntityManagerInterface $manager)
    {
        if ($_POST):

            $email=$request->request->get('email');

            $user=$repository->findOneBy(['email'=>$email]);

            if ($user):

            $transporter=(new \Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
                ->setUsername('test.testwf3@gmail.com')
                ->setPassword('testwf3dev');

            $mailer=new \Swift_Mailer($transporter);

            $mess ='Vous avez fait une demande de réinitialisation de mot de passe, veuillez cliquer sur le lien ci-dessous';
            $name = "";
            $surname = "";
            $subject = "Mot de passe oublié";
            $from ="test.testwf3@gmail.com";

            $message= (new \Swift_Message($subject))
                ->setFrom($from)
                ->setTo($email);

            $mail=$user->getId();
            $token=uniqid();
            $user->setReset($token);

            $manager->persist($user);
            $manager->flush();
            $cid=$message->embed(\Swift_Image::fromPath("upload/logo.png"));
            $message->SetBody(
                $this->render('mail/mail_template.html.twig', [
                    'from'=>$from,
                    'name'=>$name,
                    'surname'=>$surname,
                    'subject'=>$subject,
                    'message'=>$mess,
                    'logo'=>$cid,
                    'objectif'=>'Réinitialiser',
                    'liens'=> 'http://127.0.0.1:8000/resetToken/' . $mail . '/' . $token
                ]),
                'text/html'
            );
            $mailer->send($message);
                $this->addFlash('success', 'Un lien de réinitialisation vous a été envoyé à votre adresse mail');
            else:
                $this->addFlash('error', 'Aucun utilisateur ne correspond à cet Email');
                $this->redirectToRoute('forgotPassword');
            endif;

        endif;

        return $this->render('security/forgotPassword.html.twig');
    }


    /**
     * @Route("/resetToken/{email}/{token}", name="resetToken")
     */
    public function resetToken($email, $token, UserRepository $repository)
    {
        $mail=urldecode($email);
        $user =$repository->findOneBy(['id'=>$email, 'reset'=>$token]);
        if ($user):
            return $this->redirectToRoute('resetPassword',[
                'id'=>$user->getId()
            ]);

        else:
            $this->addFlash('error', 'Une erreur est survenue, veuillez refaire une demande de réinitialisation de mot de passe');
            return $this->redirectToRoute('login');
        endif;
    }


    /**
     * @Route("/resetPassword", name="resetPassword")
     */
    public function resetPassword(Request $request, EntityManagerInterface $manager, UserPasswordEncoderInterface $encoder)
    {
        if ($_POST):

        endif;

        return $this->render('security/resetPassword.html.twig');
    }
}
