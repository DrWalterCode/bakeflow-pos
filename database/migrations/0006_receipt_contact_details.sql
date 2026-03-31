UPDATE shops
SET phone = '+263 77 226 4471'
WHERE id = 1
  AND (
      phone IS NULL
      OR TRIM(phone) = ''
      OR TRIM(phone) = '+1 555 0100'
  );

UPDATE shops
SET email = 'sales@zimbocrumbbakery.co.zw'
WHERE id = 1
  AND (
      email IS NULL
      OR TRIM(email) = ''
  );

UPDATE shops
SET receipt_footer = 'We value your feedback. Please send feedback on WhatsApp to +263 77 226 4471 or email sales@zimbocrumbbakery.co.zw.'
WHERE id = 1
  AND (
      receipt_footer IS NULL
      OR TRIM(receipt_footer) = ''
      OR TRIM(receipt_footer) = 'Come back soon!'
      OR TRIM(receipt_footer) = 'Please come again.'
  );
