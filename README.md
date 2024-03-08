<h1 align="center">GIM KYC Application
<a href="#" target="_blank"><img src="https://github.com/mitmelon/gim/assets/55149512/c4f67659-a5c6-441e-b7d6-59b6bd47a404" alt="gim - General Identity Management - This is a KYC system for collecting and processing personal information's, facial biometrics, ID Cards and Signature."></a></h1>
<div align="center">
<a href="https://t.me/+7jfbiGKhn55iODlk">Join Telegram</a>
<a href="https://twitter.com/manomitehq" ><img src="https://img.shields.io/twitter/follow/manomitehq.svg?style=social" /> </a>
<br>

<i>Welcome to the General Identity Management (Gim) application, an open-source solution for developers, individuals, and businesses seeking robust identity management and Know Your Customer (KYC) capabilities.</i>

<a href="https://github.com/mitmelon/gim/stargazers"><img src="https://img.shields.io/github/stars/mitmelon/gim" alt="Stars Badge"/></a>
<a href="https://github.com/mitmelon/gim/network/members"><img src="https://img.shields.io/github/forks/mitmelon/gim" alt="Forks Badge"/></a>
<a href="https://github.com/mitmelon/gim/pulls"><img src="https://img.shields.io/github/issues-pr/mitmelon/gim" alt="Pull Requests Badge"/></a>
<a href="https://github.com/mitmelon/gim/issues"><img src="https://img.shields.io/github/issues/mitmelon/gim" alt="Issues Badge"/></a>
<a href="https://github.com/mitmelon/gim/graphs/contributors"><img alt="GitHub contributors" src="https://img.shields.io/github/contributors/mitmelon/gim?color=2b9348"></a>
<a href="https://github.com/mitmelon/gim/blob/master/LICENSE"><img src="https://img.shields.io/github/license/mitmelon/gim?color=2b9348" alt="License Badge"/></a> [![Total Downloads](http://poser.pugx.org/mitmelon/gim/downloads)](https://packagist.org/packages/mitmelon/gim)

<i>Loved the project? Please consider [donating](https://paypal.me/mitmelon) to help it improve!</i>

</div>

Gim, an open-source General Identity Management application, goes beyond traditional identity verification methods by incorporating artificial intelligence (AI) to enhance the accuracy and reliability of the process. With Gim, users can collect personal information, facial recognition data, and ID card images, which are then processed and analyzed using AI algorithms to generate a comprehensive verification score.

*Key Features:*

1. *AI-Powered Data Processing:* Gim utilizes state-of-the-art AI algorithms to process data from facial recognitions and ID card images. This advanced technology ensures accurate and efficient identity verification.

2. *Data Matching and Comparison:* The AI engine in Gim compares the data extracted from facial recognitions and ID card images with the information provided in the personal input section. By analyzing various data points, Gim generates a verification score for each identity.

3. *Scoring System:* Identity verification results are categorized into two scores:
   - *Excellent:* Data that matches between facial recognition, ID card images, and personal information input receive an excellent score, indicating a high level of confidence in the identity's authenticity.

   - *Average:* Data that does not match entirely are scored as average, prompting further review or verification steps to ensure accuracy.


*Benefits:*

- *Enhanced Accuracy:* By harnessing the power of AI, Gim achieves higher accuracy in identity verification, reducing the risk of fraudulent activities and unauthorized access.
  
- *Streamlined Processes:* The automated data processing and scoring system streamline the KYC process, saving time and resources for businesses and individuals.

- *Increased Security:* With AI-powered identity verification, Gim offers heightened security measures, protecting sensitive information and ensuring compliance with regulatory requirements.


### Features:

1. *Comprehensive Data Collection:* Gim enables the collection and secure storage of personal information, facial recognition data, ID card images, and digital signatures.

2. *Advanced Verification:* Leveraging cutting-edge facial recognition technology, Gim ensures accurate and secure identity verification, enhancing the integrity of KYC processes.

3. *Flexible Data Transmission:* Data collected through Gim can be transmitted to hosters for review via webhook or email, providing flexibility based on individual preferences.

4. *Intuitive Integration:* Gim utilizes Convex for database management, ensuring secure storage and retrieval of sensitive user information. Hosters are required to have a Convex account for seamless integration.


### Todo Features:

  - [ ] Collects Personal Informations
  - [ ] Collects facial biometrics
  - [ ] Collects ID Card (Government issued identity card)
  - [ ] Collects signatures (Digitally)
  - [ ] Stores Data on [Convex](https://dashboard.convex.dev/)
  - [ ] Generates Data on PDF
  - [ ] Receive Data through Email Address
  - [ ] Receive Data through webhook


GIM is a PHP application, you must choose a version that fits the requirements of your project. The differences between the requirements for the available versions of gim are briefly highlighted below.

|                                                              | PHP     | Support                  |
|--------------------------------------------------------------|---------|--------------------------|
| gim 1.0 and newer                                            | 8.1.0   | :heavy_check_mark: Active|

Note: gim 1.0.x works on PHP equal or greater than 8.1.


## Installing gim

<h3>Stage 1</h3>

Install this application first

|    Softwares                                                 | Version | Installation                                              |
|--------------------------------------------------------------|---------|---------------------------------------------------------- |
| NodeJs                                                       | Latest  | [NodeJs ](https://nodejs.org/en)|
| Tesseract                                                    | Latest  | [Tesseract](https://tesseract-ocr.github.io/tessdoc/Installation.html)       |
| Redis                                                        | Latest  | [Redis ](https://redis.io/download/)                      |

<h3>Stage 2</h3>

Install Gim

    git clone https://github.com/mitmelon/gim
    cd gim

<h3>Stage 3</h3>

This application uses convex as database for storing data securely. You need to have an account with [Convex](https://dashboard.convex.dev/) to get started. 

- Then create an application called GIM
- Click GIM and go to settings
- Under the URL and Deploy key
- Click Generate Development key
- Copy your keys and and url
- Open the [convex.json](settings/config/convex.json) in gim directory and configure the files such as;
- Paste your key and url as shown below and save.

    "convex_token": "PASTE_CONVEX_TOKEN_HERE",
    "convex_url": "PASTE_CONVEX_URL_HERE"


Create an account with [OpenAI](https://platform.openai.com/apps)

- Then go to API Keys and create your key
- Open the [openai](settings/config/openai.json) and paste your credentials;


Open the [app](settings/config/app.json) and configure the variables;

        "app_deployer_id": "", //Leave as blank (the application will auto set this)
        "app_domain": "", //Your application domain where this application is stored
        "app_timezone": "", //App timezone
        "app_language": "en", //App language - please note that only english is available now
        "contact": "gim-demo@manomite.net", //Application support email
        "hook_type": "email", //How to receive your user data - type [email or webhook] 
        "hook_email": "", //If its email, you are required to set the email
        "hook_secret": "" //if webhook then set the secret of your webhook [use hmac sha512 to verify the APP_NAME-X-Signature' header. Please note that the APP_NAME is a constant set from whatever you input on the "app_name"]


You can now configure the mail file with your smtp configurations to send emails if using hook_type as email
- [mail](settings/config/mail.json)

<h3>Stage 4</h3>

Open cmd and enter this command
    cd gim/app
    npx convex dev

Go to your application url and kick start it.

### Support

If your company requires support for a new feature or technical support, please open a request.

# Changelog

All notable changes to this project will be documented here.

## Contribute

Contributions are always welcome!

## License

MIT License
