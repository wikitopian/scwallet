# Stablecoin Wallet

Stablecoin Wallet is a [DAI Token](https://makerdao.com/en/whitepaper)
wallet that functions as a WooCommerce payment processing method and facilitates
simple transfers between users within the system. It relies on no third-party
services, directly interfacing by RPC with your own Ethereum node.

### Prerequisites

Stablecoin Wallet is a WordPress plugin that depends on WooCommerce and RPC
access to an Ethereum node.

PHP 7.3+ (w/ `php-bcmath` and `php-curl` extensions)
Composer 1.8+
WordPress 5.4+
WooCommerce 4.2+

### Installing

*Ubuntu Server*

The libraries depend on a couple php extensions you may not already have installed.

    sudo apt-get install php-bcmath php-curl
    cd wp-content/plugins/
    git clone --recursive https://www.github.com/wikitopian/scwallet.git
    cd scwallet

One library depends on having composer installed.

    sudo apt-get install composer
    composer install

The styling depends on SASS, and requires recompiling every time it's changed.

    sudo apt-get install ruby-full build-essential rubygems
    sudo gem install sass
    cd scwallet/styles/
    sass --watch scwallet.scss:scwallet.css

## Built With

* [jquery-qrcode](https://github.com/jeromeetienne/jquery-qrcode) - QR Code Generator

* [http-client](https://github.com/furqansiddiqui/http-client) - HTTP Client
* [ethereum-rpc](https://github.com/furqansiddiqui/ethereum-rpc) - Ethereum RPC
* [erc20-php](https://github.com/furqansiddiqui/erc20-php) - ERC20 Tokens

* [ethers.js](https://github.com/ethers-io/ethers.js/) - The Ethers Project

## Authors

* **Matt Parrott** - *Initial work* - [@wikitopian](https://github.com/wikitopian)

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details

## Acknowledgments

* [M. Furqan Siddiqui](https://www.furqansiddiqui.com/) - the hard part
