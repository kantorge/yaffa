# Yet Another Free Financial Application (YAFFA 🍊)

[![Actions Status](https://github.com/kantorge/yaffa/workflows/CI/badge.svg)](https://github.com/kantorge/yaffa/actions)

## About Yaffa

Yaffa is a personal finance web application, focusing on the support of long term financial planning. 
Yaffa is a self hosted web application, based on PHP and Laravel. 
By hosting your own app instance, your financial data is not uploaded to the cloud, and shared with any third parties.

## Key features
* Support multiple currencies.
  * There is a default currency, which is used on dashboards and charts.
  * Currency rates are automatically updated daily.
  * It is based on the free exchangerate.host service, no API keys needed.
* Support multiple accounts and account groups.
* Track your income and spending by recording transactions.
  * Yaffa tries to come up with suggestions while entering transactions to speed up transaction recording process.
  * Transactions can be split into categories. This helps to better understand spending patterns and budget planning.
  * 🆕 Yaffa can process receipts from emails and fill in transaction details automatically.
  * This feature is optional. It is based on OpenAI API. You need to register and pay for the service. 
  Also, you need to be able to receive emails on your own server. 
* Scheduled and recurring transactions, and budgets (plans) can be created.
  * This can be used to calculate forecasted data.
  * You can enable the automatic recording of recurring transactions.
* Add your investments, to calculate gain and loss.
  * Automatically retrieve investment prices.
  * This is based on alphavantage.co service. You need to register and get your free API key.

**Please note:** the application can be used in production, but it's still under development.
This means, that the interface might need improvements, some forms or flows could be optimized, 
but no data loss is expected while using the application.

There are several features **planned** to be implemented:
* Further charts, reports and dashboards.
* Handling multiple users as a family in one app instance.
* Better onboarding experience, or a tutorial.
* Documentation for installation and usage.
* QIF/CSV file imports

Several features are **not likely to be introduced**, which you might expect from such applications.
If you are looking to have these, then Yaffa might not be the right choice for you.
* Downloading transaction data directly from banks.
* Mobile app 
  * Yaffa is optimized for desktop browsers, but the interface is more or less responsive to support mobile view.

## Sandbox
Take a look at the application at https://sandbox.yaffa.cc

⚠️ **Important!** This is not a production environment or a free service. 
Use it only to experiment with YAFFA functionality and UI. 
The database is regulary wiped without prior notice.

💡 **Tip:** use a disposable email address to sign up. You need to receive the email and verify your registration,
but the email address is not required for testing the application. (Well, maybe except if you forget your password.)

## Gettings started
Installation instructions and getting started steps will be added here.

## Sponsors
The project is supported by JetBrains under [Open Source Support Program](https://www.jetbrains.com/community/opensource/#support).
<br><img src="https://resources.jetbrains.com/storage/products/company/brand/logos/PhpStorm.png" alt="PhpStorm logo" width="40%">

## License
Yaffa is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
