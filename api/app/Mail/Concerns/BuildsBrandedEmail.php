<?php

namespace App\Mail\Concerns;

trait BuildsBrandedEmail
{
    private function renderBrandedEmail(string $heading, string $bodyHtml): string
    {
        $heading = e($heading);

        return <<<HTML
        <!doctype html>
        <html>
        <body style="margin:0;padding:32px 16px;background:#f0f0f4;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;">
                  <tr>
                    <td style="padding:0 4px 20px;">
                      <table role="presentation" cellpadding="0" cellspacing="0">
                        <tr>
                          <td style="width:32px;height:32px;background:#4f46e5;border-radius:8px;text-align:center;vertical-align:middle;">
                            <span style="color:#ffffff;font-size:16px;line-height:32px;">&#9679;</span>
                          </td>
                          <td style="padding-left:10px;font-size:15px;font-weight:650;color:#111114;">Smart Inspection</td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td style="background:#ffffff;border:1px solid #e4e4ea;border-radius:12px;padding:32px;">
                      <h1 style="margin:0 0 16px;font-size:20px;line-height:26px;font-weight:650;color:#111114;letter-spacing:-0.02em;">{$heading}</h1>
                      {$bodyHtml}
                    </td>
                  </tr>
                  <tr>
                    <td style="padding:20px 4px 0;font-size:12px;line-height:18px;color:#8b8b96;text-align:center;">
                      Location is only recorded during your working hours.
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </body>
        </html>
        HTML;
    }

    private function renderCredentialRow(string $label, string $value): string
    {
        $label = e($label);
        $value = e($value);

        return <<<HTML
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #f0f0f4;font-size:13px;color:#8b8b96;">{$label}</td>
          <td style="padding:8px 0;border-bottom:1px solid #f0f0f4;font-size:14px;color:#111114;font-weight:600;text-align:right;">{$value}</td>
        </tr>
        HTML;
    }

    private function renderButton(string $url, string $label): string
    {
        $url = e($url);
        $label = e($label);

        return <<<HTML
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:24px;">
          <tr>
            <td style="background:#4f46e5;border-radius:8px;">
              <a href="{$url}" style="display:inline-block;padding:12px 20px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;">{$label}</a>
            </td>
          </tr>
        </table>
        HTML;
    }
}
