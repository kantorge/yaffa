# Yet Another Free Financial Application (YAFFA 🍊)

## About Yaffa

Yaffa is a personal finance web application, focusing on the support of long term financial planning. 
Yaffa is a self hosted web application, written in PHP, using Laravel framework. 
By hosting your own app instance, your financial data is not uploaded to the cloud, and shared with any third parties.

Read more about YAFFA at https://www.yaffa.cc

## Key features
* Support multiple currencies.
  * There is a default currency, which is used on dashboards and charts.
  * Currency rates are automatically updated daily.
  * YAFFA utilizes the free Frankfurter service, requiring no API key or registration.
* Support multiple accounts and account groups.
* Track your income and spending by recording transactions.
  * Yaffa tries to come up with suggestions while entering transactions to speed up transaction recording process.
  * Transactions can be split into categories. This helps to better understand spending patterns and budget planning.
  * Yaffa can process receipts from emails and fill in transaction details automatically.
    * This feature is optional. It is based on OpenAI API. You need to register and pay for the service. 
      Also, you need to be able to receive emails on your own server. 
* Scheduled and recurring transactions, and budgets (plans) can be created.
  * This can be used to calculate forecasted data.
  * You can enable the automatic recording of recurring transactions.
* Add your investments, to calculate gain and loss.
  * Automatically retrieve investment prices.
  * This is based on alphavantage.co service. You need to register and get your free API key.

**Please note!** While the application is suitable for production use, ongoing development may lead to interface improvements and optimizations. 
No data loss is expected while using the application, but breaking changes can occur, e.g. in the database structure.

There are several features **planned** to be implemented:
* Further charts, reports and dashboards.
* Handling multiple users as a family in one app instance.
* Better onboarding experience, or a tutorial.
* QIF/CSV file imports

Several features are **not likely to be introduced**, which you might expect from such applications.
If you are looking to have these, then Yaffa might not be the right choice for you.
* Downloading transaction data directly from banks.
* Mobile app 
  * Yaffa is optimized for desktop browsers, but the interface is more or less responsive to support mobile view.

Read more about the features of YAFFA at https://www.yaffa.cc/features

## Try it out

You can try out YAFFA without installing it. Take a look at the application at https://sandbox.yaffa.cc

Do you want to walk around, kick the tires, and see the application in action? Use the following credentials to log in:
* Username: `demo@yaffa.cc`
* Password: `demo`

Want a full test drive to explore the application as if it were your own instance?
You can register a new account at https://sandbox.yaffa.cc/register

⚠️ **Important!** This is not a production environment or a free service. 
Use it only to experiment with YAFFA's functionality and UI. 
The database is regulary wiped without prior notice.

💡 **Tip:** use a fake or disposable email address to sign up. The email address does not need to be verified.

## Getting started
Read the full documentation at https://www.yaffa.cc/documentation/,
including the [Getting Started](https://www.yaffa.cc/documentation/resources/category/getting-started/) guide
and the [Installation instructions](https://www.yaffa.cc/documentation/resources/category/installation/).

## Sponsors
The project is supported by JetBrains under [Open Source Support Program](https://www.jetbrains.com/community/opensource/#support).
<br><img src="https://resources.jetbrains.com/storage/products/company/brand/logos/PhpStorm.png" alt="PhpStorm logo" width="40%">

## License
Yaffa is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
