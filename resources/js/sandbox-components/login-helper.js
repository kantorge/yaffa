const demoLoginButton =  document.getElementById('loginWithDemoCredentials');
if (demoLoginButton) {
    demoLoginButton.addEventListener('click', function () {
        document.getElementById('email').value = 'demo@yaffa.cc';
        document.getElementById('password').value = 'demo';
        document.getElementById('login').click();
    });
}
