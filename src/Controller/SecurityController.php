<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class SecurityController extends AbstractController
{
    /**
     * @Route("/register", name="register")
     */
    public function registration(EntityManagerInterface $manager, Request $request, UserPasswordEncoderInterface $encoder)
    {
        //UserPasswordEncoderInterface pour pouvoir fonctionner attends l'objet User, que celui ci heirte de la class UserInterface, qui lui attend des méthodes bien spécifiques a implementer afin de s'assurer du bon fonctionnement de l'authentification

        $user=new User();

        $form=$this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()):

            $hash=$user->getPassword();
            $hashpassword=$encoder->encodePassword($user, $hash);
            $user->setPassword($hashpassword);

            $manager->persist($user);
            $manager->flush();

            $this->addFlash('success', 'Votre compte à bien été enregistré');
            return $this->redirectToRoute('home');
        endif;

        return $this->render('security/registration.html.twig',[
           'form'=>$form->createView()
        ]);
    }

    /**
     * @Route("/login", name="login")
     */
    public function login()
    {

        return $this->render('security/login.html.twig',[
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {

    }

}
