# ApiSkeleton - Symfony 5, JWT, and Docker !

## Aim of this project
This project is to deliver a simple dev base, with the necessary for having an API with the minimal things like :
- User ACL
- User authentification (with JWT Token)
- User actions like :
  - Register an account
  - Login an account
  - Get profile
  - Edit an account (for user and admin)
  - Create an account for admin (and sending an email)
  - Soft-delete an account (for user and admin)
  - Activate an account (with token send by mail)
  - Forget a password (with token send by mail, two route)
- Simple doc (with NelmioApiDocBundle)
- A route for sending email -- this is maybe useless, but multiple project I will create may need this function. (Maybe also the possibility to choose a SMTP Transport option)
- A docker container

And a the question : Why you don't just use API Platform ?
Very simple answer : I find than API Platform is to complete and complex for my personnal use. And if I think this, maybe another person think like me.

## Installation
- Install Docker
- It's all

## How To Use
- Git clone this project
- cd to project and "docker-compose up -d"
- In .env, configure for your use
- Go to the PHP container, in the project and bin/console doctrine:schema:create (or something like this, I don't remember)
- Generate JWT keys (with openSSL) and place them in `config/bin`
- It's done, you can visit `http://localhost:1025/doc`, and see the magic project.

(If i've forget something, don't hesitate)

## Documentation
- [Symfony 5.1 Documentation](https://symfony.com/doc/5.1/components/index.html)
- [Nelmio/Swagger GitHub](https://symfony.com/doc/current/bundles/NelmioApiDocBundle/index.html)
- [LexikJWT GitHub](https://github.com/lexik/LexikJWTAuthenticationBundle)
- StackOverflow

## Contributing
I don't know if this will be used by another people than me, so... If you want to contribute, you can!
And, like I'm new to contributing option, I don't really know how this is working... 

Maybe fork the project, make update, and pull-request here.. ? I think :)

## Remains to be done
I've created some issue for things need to be done, so you can look on them and maybe help.
Or, if you have some idea to improve this project, don't hesitate to create an issue ;)

## Additionnal ressource
This project is developped with PhpStorm, so let me present you some usefull plugin used for this project : 
- [Symfony Support](https://plugins.jetbrains.com/plugin/7219-symfony-support/)
- [Swagger](https://plugins.jetbrains.com/plugin/8347-swagger/)
- [PhpAnnotations](https://plugins.jetbrains.com/plugin/7320-php-annotations/)
- [.ignore](https://plugins.jetbrains.com/plugin/7495--ignore/)
- [.env files support](https://plugins.jetbrains.com/plugin/9525--env-files-support/)

## About me
I'm a french developper working with VueJS and Symfony on personnal time.
And I'm working on Magento on my professional time.

You can find me on [Twitter](https://twitter.com/Oryzon_) and [Twitch](https://www.twitch.tv/oryzon_)