<?php

namespace Leos\UI\RestBundle\Controller\Wallet;

use Leos\UI\RestBundle\Controller\AbstractController;

use Leos\Application\UseCase\Wallet\WalletQuery;
use Leos\Application\Request\Common\PaginationDTO;
use Leos\Application\UseCase\Transaction\Request\DepositDTO;
use Leos\Application\UseCase\Transaction\Request\WithdrawalDTO;
use Leos\Application\UseCase\Transaction\TransactionCommand;
use Leos\Application\UseCase\Transaction\Request\CreateWalletDTO;

use Leos\Domain\Wallet\Model\Wallet;
use Leos\Domain\Wallet\ValueObject\WalletId;
use Leos\Domain\Transaction\Model\AbstractTransaction;

use Leos\Infrastructure\CommonBundle\Pagination\PagerTrait;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;

use Hateoas\Representation\PaginatedRepresentation;

use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use FOS\RestBundle\Controller\Annotations\RouteResource;

use Symfony\Component\Form\Form;

/**
 * Class WalletController
 *
 * @package Leos\UI\RestBundle\Controller\Wallet
 *
 * @RouteResource("Wallet", pluralize=false)
 */
class WalletController extends AbstractController
{
    use PagerTrait;

    /**
     * @var WalletQuery
     */
    private $walletQuery;

    /**
     * @var TransactionCommand
     */
    private $transactionCommand;

    /**
     * WalletController constructor.
     *
     * @param WalletQuery $walletQuery
     * @param TransactionCommand $transactionCommand
     */
    public function __construct(WalletQuery $walletQuery, TransactionCommand $transactionCommand)
    {
        $this->walletQuery = $walletQuery;
        $this->transactionCommand = $transactionCommand;
    }

    /**
     * @ApiDoc(
     *     resource = true,
     *     section="Wallet",
     *     description = "List wallet collection",
     *     output = "Leos\Domain\Wallet\Model\Wallet",
     *     statusCodes = {
     *       201 = "Returned when successful",
     *       400 = "Returned when Bad Request",
     *       404 = "Returned when page not found"
     *     }
     * )
     *
     * @QueryParam(
     *     name="page",
     *     default="1",
     *     description="Page Number"
     * )
     * @QueryParam(
     *     name="limit",
     *     default="500",
     *     description="Items per page"
     * )
     *
     * @QueryParam(
     *     name="orderParameter",
     *     nullable=true,
     *     requirements="(real.amount|bonus.amount|createdAt|updatedAt)",
     *     map=true,
     *     description="Order Parameter"
     * )
     *
     * @QueryParam(
     *     name="orderValue",
     *     nullable=true,
     *     requirements="(ASC|DESC)",
     *     map=true,
     *     description="Order Value"
     * )
     *
     * @QueryParam(
     *     name="filterParam",
     *     nullable=true,
     *     requirements="(real.amount|bonus.amount|createdAt|updatedAt)",
     *     strict=true,
     *     map=true,
     *     description="Keys to filter"
     * )
     *
     * @QueryParam(
     *     name="filterOp",
     *     nullable=true,
     *     requirements="(gt|gte|lt|lte|eq|like|between)",
     *     strict=true,
     *     map=true,
     *     description="Operators to filter"
     * )
     *
     * @QueryParam(
     *     name="filterValue",
     *     map=true,
     *     description="Values to filter"
     * )
     *
     * @View(statusCode=200, serializerGroups={"Default", "Identifier", "Basic"})
     *
     * @param ParamFetcher $fetcher
     *
     * @return PaginatedRepresentation
     */
    public function cgetAction(ParamFetcher $fetcher): PaginatedRepresentation
    {
        $dto = new PaginationDTO($fetcher->all());

        return $this->getPagination(
            $this->walletQuery->find($dto),
            'cget_wallet',
            [],
            $dto->getLimit(),
            $dto->getPage()
        );
    }

    /**
     * @ApiDoc(
     *     resource = true,
     *     section="Wallet",
     *     description = "Gets a wallet for the given identifier",
     *     output = "Leos\Domain\Wallet\Model\Wallet",
     *     statusCodes = {
     *       200 = "Returned when successful",
     *       404 = "Returned when not found"
     *     }
     * )
     *
     * @View(statusCode=200, serializerGroups={"Identifier", "Basic"})
     *
     * @param string $walletId
     *
     * @return Wallet
     */
    public function getAction(string $walletId): Wallet
    {
        return $this->walletQuery->get(new WalletId($walletId));
    }

    /**
     * @ApiDoc(
     *     resource = true,
     *     section="Wallet",
     *     description = "Create a new Wallet",
     *     output = "Leos\Domain\Wallet\Model\Wallet",
     *     statusCodes = {
     *       201 = "Returned when successful"
     *     }
     * )
     *
     * @RequestParam(name="userId",   default="none", description="The user identifier")
     * @RequestParam(name="currency", default="EUR",  description="The currency of the wallet")
     *
     * @View(statusCode=201)
     *
     * @param ParamFetcher $fetcher
     *
     * @return \FOS\RestBundle\View\View|Form
     */
    public function postAction(ParamFetcher $fetcher)
    {
        $wallet = $this->transactionCommand->createWallet(
            new CreateWalletDTO(
                $fetcher->get('userId'),
                $fetcher->get('currency')
            )
        );

        return $this->routeRedirectView('get_wallet', [ 'walletId' => $wallet->id() ]);
    }

    /**
     * @ApiDoc(
     *     resource = true,
     *     section="Wallet",
     *     description = "Generate a positive insertion on the given Wallet",
     *     output = "Leos\Domain\Debit\Model\Debit",
     *     statusCodes = {
     *       202 = "Returned when successful",
     *       400 = "Returned when bad request",
     *       404 = "Returned when wallet not found"
     *     }
     * )
     *
     * @RequestParam(name="real",     default="0",   description="Deposit amount")
     * @RequestParam(name="currency", default="EUR", description="Currency")
     * @RequestParam(name="provider", default="", description="Payment provider")
     *
     * @View(statusCode=202, serializerGroups={"Identifier", "Basic"})
     *
     * @param string $uid
     * @param ParamFetcher $fetcher
     *
     * @return AbstractTransaction
     */
    public function postDepositAction(string $uid, ParamFetcher $fetcher): AbstractTransaction
    {
        return $this->transactionCommand->deposit(
            new DepositDTO(
                $uid,
                $fetcher->get('currency'),
                (float) $fetcher->get('real'),
                $fetcher->get('provider')
            )
        );
    }

    /**
     * @ApiDoc(
     *     resource = true,
     *     section="Wallet",
     *     description = "Generate a negative insertion on the given Wallet",
     *     output = "Leos\Domain\Payment\Model\Withdrawal",
     *     statusCodes = {
     *       202 = "Returned when successful",
     *       400 = "Returned when bad request",
     *       404 = "Returned when wallet not found",
     *       409 = "Returned when not enough founds"
     *     }
     * )
     *
     * @RequestParam(name="real",     default="0",  description="Withdrawal amount")
     * @RequestParam(name="currency", default="EUR", description="Currency")
     * @RequestParam(name="provider", default="", description="Payment provider")
     *
     * @View(statusCode=202, serializerGroups={"Identifier", "Basic"})
     *
     * @param string $uid
     * @param ParamFetcher $fetcher
     *
     * @return AbstractTransaction
     */
    public function postWithdrawalAction(string $uid, ParamFetcher $fetcher): AbstractTransaction
    {
        return $this->transactionCommand->withdrawal(
            new WithdrawalDTO(
                $uid,
                $fetcher->get('currency'),
                (float) $fetcher->get('real'),
                $fetcher->get('provider')
            )
        );
    }
}
