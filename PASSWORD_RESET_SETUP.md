# Customer password recovery

Customer password recovery is admin-assisted so a password cannot be changed
just by knowing the customer's email address.

1. The customer uses **Forgot password?** on the login page and contacts the
   Purple Blend administrator.
2. The administrator verifies the customer's identity outside the application.
3. In Admin, open **Reset Customer Password**, enter the registered email, and
   set a temporary password of at least eight characters.
4. Share the temporary password privately. The customer signs in and changes
   it from their account settings.

This local flow does not require SMTP or any external integration. Do not reset
an account until the customer's identity has been verified.
