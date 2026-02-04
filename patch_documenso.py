import os

path = '/var/www/documenso-source/apps/remix/app/components/general/document-signing/document-signing-complete-dialog.tsx'

with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Imports
if 'Checkbox' not in content:
    content = content.replace(
        "import { Button } from '@documenso/ui/primitives/button';", 
        "import { Button } from '@documenso/ui/primitives/button';\nimport { Checkbox } from '@documenso/ui/primitives/checkbox';"
    )

# 2. State
if 'termsAccepted' not in content:
    content = content.replace(
        "const { isNameLocked, isEmailLocked } = useEmbedSigningContext() || {};",
        "const { isNameLocked, isEmailLocked } = useEmbedSigningContext() || {};\n  const [termsAccepted, setTermsAccepted] = useState(false);"
    )

# 3. Checkbox UI
# Using single quotes for JSX props to avoid python string escaping madness if possible, but python triple string is fine.
# But we need to match what React expects. Double quotes are standard.
checkbox_ui = """
                  <div className="my-4 flex items-start space-x-2">
                    <Checkbox id="terms" className="mt-1" checked={termsAccepted} onCheckedChange={(checked) => setTermsAccepted(checked === true)} />
                    <div className="grid gap-1.5 leading-none">
                      <label htmlFor="terms" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                        Я принимаю условия Правил <a href="https://matrang.com/rules" target="_blank" rel="noopener noreferrer" className="underline text-primary">https://matrang.com/rules</a> и условия Договора купли-продажи
                      </label>
                    </div>
                  </div>
"""

if '<DocumentSigningDisclosure />' in content:
    content = content.replace('<DocumentSigningDisclosure />', checkbox_ui)

# 4. Disabled Logic
if 'disabled={!isComplete}' in content:
    content = content.replace('disabled={!isComplete}', 'disabled={!isComplete || !termsAccepted}')

# 5. Footer Text
footer_text = """
                  </DialogFooter>
                  <p className="mt-4 text-center text-xs text-muted-foreground">
                    Нажимая кнопку «Подписать», вы подтверждаете, что данная подпись является вашей собственноручной, а указанный Email принадлежит вам. Вы согласны с тем, что технические данные о вашем IP и времени подписи будут занесены в Журнал Аудита.
                  </p>
"""

if '</DialogFooter>' in content and 'Журнал Аудита' not in content:
    content = content.replace('</DialogFooter>', '  ' + footer_text)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Patch applied successfully")
