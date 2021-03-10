<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\AbstractType;

use App\Entity\Supplier;
use App\Entity\Recipient;
use App\Entity\Position;

use App\Repository\SupplierRepository;
use App\Repository\RecipientRepository;
use App\Repository\PositionRepository;

use App\Entity\Invoice;
use App\Form\Type\InvoiceType;
use App\Repository\InvoiceRepository;

use App\Entity\InvoicePosition;
use App\Form\Type\InvoicePositionType;
use App\Repository\InvoicePositionRepository;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\EntityManagerInterface;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class InvoiceAddController extends AbstractController
{
    private $session;
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function invoice_add(Request $request, $session) : Response
    {     
        $note_position = 0;
        $note_invoice = 0;
        $integer = true;
        $zero = 1;
                
        if ( $this->session->get('sessionInvoicePositionsArray') != null and $session == 1)  {
            $invoicePositionsArray = $this->session->get('sessionInvoicePositionsArray');
        }
        else {
            $invoicePositionsArray = array();
        }

        $invoicePosition = new InvoicePosition;
        $form_position = $this  -> createForm(InvoicePositionType::class, $invoicePosition, ['method' => 'GET'])
                               
                                -> add('invoice_position_add', HiddenType::class, ['mapped' => false])
                                -> add ('send', SubmitType::class, ['label' => 'Add chosen positions']);
                                
                                
        $form_position->handleRequest($request);
                
        if ($form_position->isSubmitted() ) {
            
            $position = $form_position->get('position')->getData();
            $quantity = $form_position->get('quantity')->getData();
            
        //validation of quantity field:
            if (!is_numeric($quantity) or  ($quantity - floor( $quantity) ) != 0) {
                $integer = false;
            }

            if ($quantity == '0') {
                $zero = 0;
            }
               
            if ($position == null) {
                $note_position = 1;
            }
            else {
                $note_position = 2;
            }
            
            if ($integer == true  and $zero == 1 and $note_position == 2) {
                $note_position = 3;

                array_push($invoicePositionsArray, $invoicePosition);
                $this->session->set('sessionInvoicePositionsArray', $invoicePositionsArray  );

                if ($session == 0) {
                    $request= Request::createFromGlobals();
                    $this->session->set('sessionInvoicePositionsArray', null);
                    $invoice_position = $request->query->get('invoice_position');
                    
                    return $this->redirectToRoute( 'invoice_add', ['session'=> 1,'invoice_position'=>$invoice_position]);
                }

            }
           
        }
                
        $invoice = new Invoice();
        $form = $this->createForm (InvoiceType::class, $invoice)
                        ->add('supplier', EntityType::class, [      'label'=>'Supplier (type Name or NIP):',
                                                                    'class' => Supplier::class,
                                                                    'choices' =>[],
                                                                    'attr' => array('class' => 'js-select2-invoice-supplier')   
                                                                ])

                        ->add('recipient', EntityType::class, [     'label'=>'Recipient (type Name, Family or Address):',
                                                                    'class' => Recipient::class,
                                                                    'choices' =>[],
                                                                    'attr' => array('class' => 'js-select2-invoice-recipient')   
                                                            ])
                        ->add('invoicePosition', HiddenType::class, ['mapped' => false])
                        ->add('invoice_add', HiddenType::class, ['mapped' => false])
                        ->add('send', SubmitType::class, ['label'=>'Create invoice']);

        $form->handleRequest($request);

        if ($form->isSubmitted() ) {
            
            $supplier = $form->get('supplier')->getData();
            $recipient = $form->get('recipient')->getData();
            
            
    //validation of position field:        
            if ($supplier == null or $recipient == null) {
                $note_invoice = 1;
            }
            else {
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($invoice);
                $entityManager->flush();
                $note_invoice = 2;
            }       
        }

        $contents = $this->renderView('invoice_add/invoice_add.html.twig', [
                    
            'form' => $form->createView(),
            'form_position' => $form_position -> createView(),
            'note_invoice' => $note_invoice,
            'note_position' => $note_position,
            'integer' => $integer,
            'zero' => $zero,
            'invoicePositionsArray' => $invoicePositionsArray,

        ]);     
       
               
        return new Response ($contents);  
    } 
    
    public function position_add ($id_invoice, $id_position)
    {
                      
    // extracting array of InvoicePosition objects for the chosen invoice and position        
        $invoicePositionManager = $this->getDoctrine()->getManager();
        $invoicePositionArray = $invoicePositionManager->getRepository(InvoicePosition::class)
                                                        ->findBy ([
                                                            'invoice' => $id_invoice, 
                                                            'position' => $id_position 
                                                        ]);
        

    //checking if this position is already exist in the invoice and adding new position if not exist
        if (empty($invoicePositionArray) ) { 
                
            $positionManager = $this->getDoctrine()->getManager();
            $position = $positionManager->getRepository(Position::class)->find($id_position);

            $invoiceManager = $this->getDoctrine()->getManager();
            $invoice = $invoiceManager->getRepository(Invoice::class)->find($id_invoice);
            
            $invoicePosition = new InvoicePosition();
            $invoicePosition->setInvoice($invoice);
            $invoicePosition->setPosition($position);
            $invoicePosition->setQuantity(1);
                        
        }
        else {
    // changing the quantity for +1 
            $invoicePosition =  $invoicePositionArray[0];
            $quantity=$invoicePosition->getQuantity();
            $invoicePosition->setQuantity($quantity + 1);
        }

        $invoicePositionManager ->persist($invoicePosition);
        $invoicePositionManager ->flush();
        
    //reconstraction of chosen/not chosen positions (by getting saved GET form parameters) 
        $requestForm=$this->session->get('sessionForm'); 
        
        return $this->redirectToRoute( 'invoice_edit', ['id_invoice'=> $id_invoice,
                                                        'form'=>$requestForm]);
    }

    public function position_delete ($id_invoice, $id_position)
    {
    // extracting array of InvoicePosition objects for the chosen invoice and position     
        $invoicePositionManager = $this->getDoctrine()->getManager();
        $invoicePositionArray = $this->getDoctrine()->getRepository(InvoicePosition::class)
                                                ->findBy ([
                                                    'invoice' => $id_invoice, 
                                                    'position' => $id_position 
                                                ]);
        $invoicePosition =  $invoicePositionArray[0];

    // delete the position 
        if ($invoicePosition->getQuantity()==1) {
            $invoicePositionManager->remove($invoicePosition);
            $invoicePositionManager->flush();

        }
        else {
            $quantity=$invoicePosition->getQuantity();
            $invoicePosition->setQuantity($quantity - 1);
            $invoicePositionManager ->persist($invoicePosition);
            $invoicePositionManager ->flush();
        }
    
    //reconstraction of chosen/not chosen positions (by getting saved GET 'form' parameters) 
        $requestForm=$this->session->get('sessionForm'); 
        
        return $this->redirectToRoute( 'invoice_edit', ['id_invoice'=> $id_invoice,
                                                        'form'=>$requestForm]);
            
        }

        
}
