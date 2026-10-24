# لوحة إدارة موقع مؤسسة بيوت مصر

> الجزء الأول من هذا الدليل لفريق المؤسسة (المحررين والمديرين). الجزء الثاني (بالإنجليزية) للمطوّر المسؤول عن الاستضافة.

لوحة الإدارة تتيح تحديث النصوص، وأرقام الإنجازات، والمشروعات وصورها ومراحلها، والحكايات، والتقارير، ومعرض صور الأنشطة، وبيانات التواصل والهوية القانونية ووسائل التبرع، دون تعديل الكود، كما نصّت وثيقة المحتوى (القسم 12: «إدارة المحتوى» و«الخصوصية»). بعد كل حفظ تُعاد كتابة صفحات الموقع تلقائيًا خلال ثوانٍ.

---

## 1. الدخول

- افتح عنوان الموقع وبعده `/admin/` (مثل: `https://www.example.org/admin/`). استخدم دائمًا عنوانًا يبدأ بـ `https://`.
- اكتب اسم المستخدم (بالإنجليزية) وكلمة المرور.
- بعد **5 محاولات غير صحيحة** يتوقف الدخول مؤقتًا **15 دقيقة**. تظهر رسالة واحدة عامة في كل الحالات حفاظًا على الأمان.
- تنتهي الجلسة تلقائيًا بعد **30 دقيقة دون نشاط**، أو بعد **8 ساعات** متواصلة. أثناء الكتابة في النماذج تبقى الجلسة نشطة، وتظهر رسالة تنبيه قبل انتهائها.
- نسيت كلمة المرور؟ اطلب من «المدير» إعادة تعيينها من صفحة «المستخدمون».
- اضغط «تسجيل الخروج» عند الانتهاء، خاصة على الأجهزة المشتركة.

## 2. الأدوار

| الدور | ما يستطيع فعله |
|---|---|
| **مدير** | كل الأقسام: النصوص، والإعدادات، والمستخدمون، وسجل التغييرات، والنسخ الاحتياطية، وأقسام المحتوى. |
| **محرر** | النصوص، وأثرنا بالأرقام، والمشروعات، والحكايات، والتقارير، ومعرض صور الأنشطة. لا يرى الإعدادات ولا المستخدمين ولا النسخ الاحتياطية. |

القائمة تعرض لكل شخص الأقسام المسموح له بها فقط، ويُطبَّق المنع على الخادم أيضًا.

## 3. لوحة التحكم

- **ملخص المحتوى**: عدد المشروعات والحكايات والتقارير وصور الأنشطة (والمنشور منها).
- **آخر تحديث للموقع**: متى أُعيد بناء الصفحات ومن قام بذلك ونتيجته. زر **«إعادة بناء الموقع»** يعيد كتابة كل الصفحات (استخدمه إن بدت صفحة غير محدّثة).
- **آخر التغييرات**: أحدث التعديلات (المحرر يرى تعديلاته فقط).
- **قائمة المراجعة قبل إطلاق الموقع** (من القسم 13 في وثيقة المحتوى): تفحص اللوحة تلقائيًا ما يمكن فحصه، مثل:
  - أرقام الإنجازات بلا قيمة أو بلا تاريخ مراجعة.
  - عنوان الموقع (النطاق) غير مُدخل.
  - جهة الإشهار، وصفة النفع العام، وتصريح جمع التبرعات غير مُدخلة.
  - التبرع عبر الموقع غير مفعّل.
  - البريد الإلكتروني، وصفحات التواصل الرسمية، والعنوان التفصيلي، وساعات استقبال الرسائل غير مُدخلة.

  أما البنود اليدوية (مطابقة رقم الإشهار، واعتماد الصور، ومراجعة سياسة الخصوصية مع المختص القانوني، وتدقيق الأنشطة) فتراجعها إدارة المؤسسة.
- **تنبيهات الاستضافة** (للمدير فقط): تظهر إذا كان مجلد غير قابل للكتابة أو إضافة PHP ناقصة.

## 4. النصوص

كل نصوص الموقع كما وردت في وثيقة المحتوى، مقسّمة حسب الصفحات (الصفحة الرئيسية، من نحن، الأنشطة…).

1. استخدم **مربع البحث** أعلى الصفحة لإيجاد نص بكلمة منه أو باسم الحقل؛ تُفتح الأقسام المطابقة تلقائيًا.
2. أو اضغط اسم القسم لفتحه.
3. عدّل النص ثم اضغط زر **«حفظ «اسم القسم»»** أسفل القسم. يُحفظ كل قسم وحده، ثم يُحدَّث الموقع.

ملاحظات مهمة:

- **القوائم** (مثل أنواع المساعدات أو الخطوات): اكتب **كل عنصر في سطر مستقل**. السطر الفارغ يُتجاهل.
- **الأسئلة الشائعة** في «تواصل معنا»: يمكنك إضافة سؤال بزر «إضافة سؤال» أو حذف سؤال بزر «حذف». لا يُحذف شيء نهائيًا قبل الضغط على «حفظ».
- **ما بين القوسين { }** مثل `{hotline}` أو `{registration_number}` متغيرات تُملأ تلقائيًا من «الإعدادات» (رقم الخط الساخن، رقم الإشهار…). لا تترجمها ولا تغيّر كتابتها. تحت كل حقل يحتوي متغيرات شرح لمعناها.
- الحقل الذي يظهر على الموقع لا يمكن تركه فارغًا.
- إن عدّل زميل القسم نفسه أثناء عملك، تظهر رسالة «عدّل مستخدم آخر هذا القسم» وتبقى تعديلاتك في الحقول؛ راجعها واضغط «حفظ» مرة أخرى.
- إن تعذّر تحديث الموقع بعد الحفظ، **يُعاد المحتوى السابق تلقائيًا** ولا يتأثر الموقع، وتبقى تعديلاتك ظاهرة لتعيد المحاولة أو ترسل «التفاصيل التقنية» إلى المطوّر.
- إذا حاولت مغادرة الصفحة وفيها تعديلات غير محفوظة، يسألك المتصفح أولًا.
- **بعد تحديث الكود** (رفع ملف التحديث من المطوّر) قد تظهر حقول جديدة لنصوص أضافها التحديث، أو قسم جديد كامل. تظهر هذه الحقول هنا فورًا بنصها الافتراضي من وثيقة المحتوى، كما تظهر على الموقع، ويمكنك تعديلها مثل أي نص آخر. لا تُنسخ إلى ملف النصوص إلا عند حفظ قسمها بعد تعديل فيه، أما نصوصك التي عدّلتها من قبل فلا يغيّرها التحديث أبدًا.

## 5. أثرنا بالأرقام

بطاقات الأرقام التي تظهر في الصفحة الرئيسية وصفحة «إنجازاتنا» وصفحات الأنشطة.

- **القاعدة الذهبية: لا تُنشر أرقام غير موثقة.** يظهر الرقم على الموقع **فقط** إذا كانت له قيمة **و**أُدخل «تاريخ مراجعة الأرقام». البطاقة بلا قيمة تظهر للزوار بعبارة «يُعلن بعد التوثيق».
- **تاريخ مراجعة الأرقام**: تاريخ آخر مراجعة واعتماد للأرقام من إدارة المؤسسة (لا يكون في المستقبل). **الفترة**: مثل «منذ التأسيس حتى سبتمبر 2026». يظهران للزوار بجوار الأرقام.
- لكل بطاقة: **العنوان**، و**الرقم** (أرقام فقط، وتُقبل الأرقام العربية ٠١٢٣؛ اتركه فارغًا حتى يُعتمد)، و**لاحقة** اختيارية تظهر بعد الرقم (مثل «+»)، و**أيقونة** من قائمة جاهزة، و**النشاط** الذي تظهر البطاقة في صفحته أيضًا، ومربعا «يظهر في الصفحة الرئيسية» و«يظهر في صفحة إنجازاتنا».
- **إضافة بطاقة** بزر «إضافة بطاقة رقم»، و**حذفها** بزر «حذف» داخلها. لا يُحذف شيء قبل الضغط على «حفظ الأرقام».
- بجوار كل بطاقة شارة توضح حالتها الآن: «يظهر على الموقع» أو «يُعلن بعد التوثيق».
- عند نشر أرقام جديدة أو تغيير رقم ظاهر أو تاريخ مراجعته يجب وضع علامة على: «أؤكد أن الأرقام التي ستظهر على الموقع موثقة ومعتمدة من إدارة المؤسسة وتخص الفترة المذكورة».

## 6. المشروعات

قائمة المشروعات (الجارية أولًا ثم الأحدث تحديثًا) مع شارات: الحالة (جارٍ/منجز)، والنشر (منشور/غير منشور)، وتاريخ آخر تحديث (وشارة «بلا تاريخ تحديث» إن لم يُدخل). يمكن تصفية القائمة: الكل، جارٍ، منجز، غير منشورة.

**المشروع المثال «مسجد سيدنا عيسى ابن مريم — الطور، جنوب سيناء»** (من وثيقة المحتوى، القسم 08: «مثال على مشروع قابل للعرض بعد التوثيق») يصل مع الموقع **مسودةً غير منشورة** وبلا تاريخ تحديث. انشره بعد إدخال تاريخ آخر تحديث وإضافة صور التنفيذ المعتمدة ومراحل التنفيذ (تاريخ وعنوان ووصف لكل مرحلة)؛ وحتى ذلك الحين تعرض صفحات الموقع أن المشروعات الموثقة تُعرض فور اعتمادها.

**إضافة مشروع أو تعديله** (زر «إضافة مشروع» أو «تعديل»):

1. **بيانات المشروع**: الاسم (مطلوب)، والنشاط التابع له (مطلوب)، والموقع العام (المدينة أو المحافظة فقط، دون عنوان تفصيلي)، والهدف، و**الرابط بالإنجليزية** (اختياري: حروف إنجليزية صغيرة وأرقام وشرطة، مثل `masjid-el-tor`؛ إن تُرك فارغًا يُنشأ تلقائيًا. تغييره لاحقًا يغيّر عنوان صفحة المشروع).
2. **الحالة والنشر**:
   - «جارٍ» أو «منجز». **لا يوصف المشروع بأنه منجز إلا بعد إثبات الإنجاز الفعلي**: عند تغيير الحالة إلى «منجز» يجب وضع علامة على «تم إثبات الإنجاز الفعلي للمشروع»، وإلا يرفض النظام الحفظ.
   - **تاريخ آخر تحديث** يظهر للزوار (لا يكون في المستقبل)، وهو **مطلوب لنشر المشروع**: «كل مشروع له حالة وتاريخ آخر تحديث» (وثيقة المحتوى، القسم 12). إن وضعت علامة على «نشر المشروع» دون تاريخ يرفض النظام الحفظ وتبقى البيانات التي كتبتها في النموذج؛ أدخل التاريخ أو احفظ المشروع مسودة.
   - **نشر المشروع على الموقع**: غير المنشور يبقى في لوحة الإدارة فقط (مسودة) ولا تُنشأ له صفحة. عند إلغاء النشر تختفي صفحته من الموقع، وتعود عند إعادة النشر.
3. **عن المشروع**: ملخص قصير يظهر في البطاقة وأعلى الصفحة، ووصف تفصيلي (سطر فارغ بين الفقرات).
4. **التكلفة والتبرعات**: تُكتب بالجنيه (أرقام فقط)، ولا تظهر على الموقع إلا بوضع علامة على «إظهار التكلفة والتبرعات» بعد اعتماد بياناتها.
5. **صور التنفيذ**: انظر «إدارة الصور» أدناه. الصورة الأولى هي صورة الغلاف في بطاقة المشروع.
6. اضغط **«حفظ المشروع»**.

**مراحل التنفيذ** (تظهر في صفحة تعديل المشروع بعد إضافته):

- «إضافة مرحلة»: التاريخ (مطلوب، لا يكون في المستقبل)، والعنوان (مطلوب)، والوصف، والصور.
- مربع «اجعل آخر تحديث للمشروع تاريخ هذه المرحلة» (مفعّل افتراضيًا للمراحل الجديدة) يحدّث تاريخ آخر تحديث إن كانت المرحلة أحدث.
- تظهر المراحل في صفحة المشروع من الأحدث إلى الأقدم. «تعديل» و«حذف» بجوار كل مرحلة؛ حذف المرحلة يحذف صورها.

**حذف المشروع**: من أسفل صفحة التعديل («حذف المشروع نهائيًا»، مع رسالة تأكيد). يحذف المشروع وصفحته وكل صوره وصور مراحله. لإخفائه مؤقتًا ألغِ النشر بدلًا من الحذف.

## 7. الحكايات

- اختر **نوع الحكاية**: حكاية علاج، أو رزق، أو سقف، أو نجاح، أو مسجد. يظهر تحت الاختيار **دليل كتابة هذا النوع** (من وثيقة المحتوى، ويمكن تعديله من «النصوص ← حكايات من بيوت مصر ← أنواع الحكايات»).
- العنوان والملخص (مطلوبان)، ونص الحكاية، والنشاط المرتبط (اختياري)، والتاريخ، والرابط بالإنجليزية (اختياري)، والصور.
- **الخصوصية أولًا**: لا أسماء ولا عناوين ولا مدارس ولا وجوه ولا أي تفاصيل تكشف هوية أصحاب الحكاية، إلا وفق ضوابط واضحة وموافقات مناسبة. احكِ عن الأثر لا عن الحاجة.
- **نشر الحكاية** يتطلب وضع علامة على «راجعت الحكاية وصورها…». ويُطلب التأكيد نفسه عند **أي تعديل** على حكاية منشورة. إلغاء النشر لا يحتاج تأكيدًا.
- الحكاية غير المنشورة لا تظهر للزوار. حتى تُنشر أول حكاية تعرض صفحة الحكايات «الحكايات في الطريق».
- حذف الحكاية يحذف صفحتها وصورها.

## 8. التقارير

- «إضافة تقرير»: العنوان (مطلوب)، والسنة، ووصف قصير، و**ملف PDF** (مطلوب؛ PDF فقط حتى 15 ميجابايت أو حد الاستضافة).
- تظهر التقارير المنشورة في صفحة «إنجازاتنا» من الأحدث إلى الأقدم، مع زر تحميل.
- **النشر** يتطلب تأكيد أن إدارة المؤسسة راجعت التقرير واعتمدت نشره وأنه لا يتضمن بيانات شخصية للمستفيدين. ويُطلب التأكيد نفسه عند استبدال ملف تقرير منشور.
- قبل الرفع: لا صور بطاقات ولا مستندات طبية ولا بيانات شخصية، ويُفضّل حذف اسم كاتب الملف من خصائصه عند تصديره (لا تُزال بيانات ملفات PDF تلقائيًا كما في الصور).
- «استبدال الملف» يرفع نسخة جديدة ويحذف القديمة بعد الحفظ. حذف التقرير يحذف ملفه.

## 9. معرض صور الأنشطة

- تعرض الصفحة الأنشطة العشرة مع عدد صور كل نشاط. اضغط النشاط لإدارة صوره.
- تظهر الصور في صفحة النشاط تحت «صور من العمل» بالترتيب نفسه. النشاط بلا صور يعرض للزوار أن الصور تُضاف بعد اعتمادها.
- الإضافة والترتيب والحذف كما في «إدارة الصور» أدناه، ثم «حفظ الصور».

## 10. إدارة الصور (المشروعات والمراحل والحكايات والمعرض)

- **إضافة صور**: «اختر صورة أو أكثر» (يمكن اختيار عدة صور معًا؛ يظهر الحد في المرة الواحدة تحت الحقل). بعد الاختيار تظهر معاينة لكل صورة مع حقلين: **«وصف هذه الصورة (بالعربية)»** وهو مطلوب، و«تعليق تحت الصورة» اختياري.
- **الوصف (النص البديل) مطلوب لكل صورة ويُكتب بالعربية**، مثل «أعمال صب سقف المسجد»؛ يقرؤه قارئ الشاشة لمن لا يرى الصورة.
- **الترتيب**: زرّا «تقديم» و«تأخير» تحت كل صورة. يتغير الترتيب فورًا على الشاشة، ويُعتمد بعد «حفظ».
- **الحذف**: ضع علامة على «حذف الصورة» فتظهر عليها عبارة «ستُحذف عند الحفظ». بعد الحفظ يُحذف ملف الصورة من الاستضافة نهائيًا.
- إن رُفضت صورة (نوع غير مسموح أو حجم كبير) تُحفظ بقية الصور وتظهر رسالة بالصورة المرفوضة وسببها.
- إن ظهرت أخطاء في النموذج قبل الحفظ، **اختر الصور مرة أخرى** بعد تصحيحها (المتصفح لا يحتفظ بالملفات المختارة).
- تُزال بيانات الموقع الجغرافي (GPS) وبيانات الكاميرا تلقائيًا، وتُصغَّر الصور إلى 1600 بكسل عرضًا مع نسخة مصغرة للبطاقات.

## 11. الإعدادات (للمدير)

- **عنوان الموقع (النطاق)**: الرابط الكامل بعد ربط النطاق، مثل `https://www.example.org`. يفعّل خريطة الموقع لمحركات البحث.
- **الشعار**: صورة PNG بخلفية شفافة يُفضّل (أو JPG/WebP). ملفات SVG غير مقبولة لأسباب أمنية. يمكن إزالة الشعار المرفوع للعودة إلى الشعار المرسوم.
- **التواصل**: الخط الساخن (مطلوب)، وواتساب المؤسسة، وواتساب «إحنا معاكي»، والبريد، والعنوان، وساعات استقبال الرسائل، ورابط الخريطة. الأرقام العربية (٠١٢…) تُحوَّل تلقائيًا.
- **الصفحات الرسمية**: روابط كاملة تبدأ بـ `https://`.
- **الهوية القانونية**: لا تُكتب صفة النفع العام وتصاريح جمع التبرعات إلا بعد إثباتها وبالنص المطابق للمستندات المعتمدة (أرقامها ونطاقها وتواريخ صلاحيتها).
- **التبرعات**:
  - لا تُنشر أي وسيلة تبرع إلا بعد التأكد من ملكية الحساب البنكي أو المحفظة باسم المؤسسة ومن تصريح جمع التبرعات. استخدم روابط التبرع الرسمية فقط.
  - **رابط التبرع يجب أن يكون آمنًا ويبدأ بـ `https://`**؛ الموقع لا يعرض غير ذلك، لذا يرفض النموذج أي رابط يبدأ بـ `http://` برسالة واضحة ويبقي ما كتبته لتصححه. انسخ الرابط الرسمي الآمن من صفحة البنك أو الجهة المصدرة.
  - لكل وسيلة اسم، ومعه بياناتها (رقم الحساب أو المحفظة…) أو رابطها الرسمي أو كلاهما. تظهر الوسائل وأزرار «ساهم» في كل الصفحات معًا (شارك في الخير، والأنشطة، والمشروعات) فقط عند تفعيل «إظهار وسائل التبرع» ووجود وسيلة واحدة صالحة على الأقل.
  - عند تفعيل «إظهار وسائل التبرع» أو تغيير الوسائل يجب وضع علامة على إقرار التحقق قبل الحفظ.
  - عند الإيقاف تعرض صفحة «شارك في الخير» أن وسائل التبرع تُعلن فور اعتمادها.

## 12. الصور والخصوصية

قبل رفع أي صورة (القسمان 12 و13 في وثيقة المحتوى):

- **لا ترفع** صور بطاقات الرقم القومي، ولا المستندات، ولا التقارير الطبية.
- **لا ترفع** صورًا تظهر فيها وجوه أو أسماء أو عناوين يمكن أن تكشف هوية المستفيدين، إلا بموافقة واضحة ومسوّغ مناسب.
- اكتب **وصفًا قصيرًا للصورة** (النص البديل) لكل صورة؛ يقرؤه قارئ الشاشة لمن لا يرى الصورة.
- تُزال بيانات الموقع الجغرافي (GPS) وبيانات الكاميرا من الصور تلقائيًا، ويُصغَّر حجمها، وتُصحَّح صور الهاتف المقلوبة.
- الصيغ المقبولة: JPG وPNG وWebP حتى 10 ميجابايت (أو أقل إن كانت حدود الاستضافة أقل؛ يظهر الحد الفعلي تحت حقل الرفع). التقارير: PDF فقط حتى 15 ميجابايت.

## 13. المستخدمون وحسابي

- **المستخدمون** (للمدير): إضافة مستخدم (الاسم، اسم المستخدم بالإنجليزية، الدور، كلمة مرور لا تقل عن 10 أحرف)، وتغيير الدور، وإعادة تعيين كلمة المرور (تُنهي جلسات المستخدم المفتوحة)، وحذف الحساب. لا يمكن حذف آخر مدير أو تغيير دوره، ولا يمكنك حذف حسابك.
- **حسابي** (للجميع): تغيير الاسم الظاهر، وتغيير كلمة المرور (تُطلب كلمة المرور الحالية).

## 14. سجل التغييرات (للمدير)

يسجّل كل حفظ وتسجيل دخول (ناجح أو غير ناجح) وتغيير في الحسابات: من، ومتى، وماذا. يمكن التصفية حسب المستخدم أو القسم.

## 15. نسخة احتياطية (للمدير)

- **تنزيل نسخة كاملة (ZIP)**: ملفات المحتوى وكل الصور وملفات PDF. لا تتضمن حسابات المستخدمين. احفظها في مكان آمن، ويُنصح بتنزيل نسخة كل شهر وقبل أي تعديل كبير.
- **النسخ التلقائية**: قبل كل حفظ تُحفظ نسخة من الملف السابق (آخر 30 نسخة لكل ملف). يمكنك **تنزيل** أي نسخة أو **استرجاعها**؛ والاسترجاع نفسه يحفظ المحتوى الحالي أولًا، فيمكن التراجع عنه.
- **تنبيه:** حذف صورة أو تقرير أو مشروع أو حكاية يحذف ملفاتها من الاستضافة نهائيًا؛ استرجاع نسخة سابقة من ملف المحتوى لا يعيد هذه الملفات. لاستعادتها تحتاج نسخة ZIP كاملة نُزّلت قبل الحذف.

## 16. رسائل قد تراها

| الرسالة | ماذا تفعل |
|---|---|
| «انتهت صلاحية الصفحة» | أعد تحميل الصفحة ثم أعد المحاولة؛ لم يُحفظ شيء. |
| «انتهت الجلسة…» | سجّل الدخول مرة أخرى. |
| «المجلد … غير قابل للكتابة» | أرسل الرسالة إلى المطوّر/مسؤول الاستضافة (انظر الجزء الإنجليزي: Permissions). |
| «حجم ما أرسلته أكبر من الحد…» | ارفع صورًا أقل أو أصغر في كل مرة. |
| «تعذّر تحديث صفحات الموقع… فأُعيد المحتوى السابق» | لم يتغير شيء على الموقع. أرسل «التفاصيل التقنية» إلى المطوّر. |
| «عدّل مستخدم آخر … أثناء عملك» | لم يُحفظ شيء بعد. تعديلاتك ما زالت في الحقول؛ راجعها (أو أعد تحميل الصفحة لترى النسخة الأحدث) ثم احفظ مرة أخرى. |
| «لم تُرفع الصور التي اخترتها بعد…» | صحّح الأخطاء المذكورة ثم اختر الصور مرة أخرى واضغط «حفظ». |
| «تعذّر رفع بعض الصور: …» | حُفظت بقية الصور. الصورة المذكورة ليست JPG/PNG/WebP أو أكبر من الحد؛ احفظها بصيغة JPG وأعد رفعها. |
| «لا يوصف المشروع بأنه منجز…» | ضع علامة على «تم إثبات الإنجاز الفعلي للمشروع» بعد التأكد، أو أبقِ الحالة «جارٍ». |
| «لا يُنشر المشروع دون «تاريخ آخر تحديث»…» | أدخل تاريخ آخر تحديث للمشروع، أو ألغِ اختيار «نشر المشروع على الموقع» واحفظه مسودة. ما كتبته باقٍ في النموذج. |
| «رابط التبرع (…): يُقبل رابط التبرع الآمن فقط…» | استبدل الرابط برابط يبدأ بـ `https://` من الجهة الرسمية، ثم احفظ. |
| «قبل نشر الحكاية أكّد مراجعة الخصوصية…» / «أكّد أن الأرقام … موثقة» / «أكّد أن إدارة المؤسسة راجعت التقرير…» | راجع المحتوى ثم ضع علامة على مربع التأكيد واحفظ. |

---

# Setup for the developer

## Requirements

* **PHP 7.4 or newer** (8.x fine). No database, no Composer, no external libraries.
* Extensions: `session`, `json` (always on); **`gd`** with JPEG/PNG (WebP optional) for image uploads; **`fileinfo`** for upload type checks; `exif` (optional — auto-rotates phone photos); `zip` (optional — ZIP backup download); `mbstring` (optional).
* Works when the host disables `set_time_limit`, `ini_set`, `ignore_user_abort` or `chmod` (`disable_functions`): since PHP 8 a disabled function is a fatal "undefined function" even with `@`, so every such call is guarded with `function_exists()` and the session options are passed to `session_start()`.
* Recommended php.ini values (Turbify → PHP settings, or a `.user.ini`): `upload_max_filesize = 16M`, `post_max_size = 20M`, `memory_limit = 256M`, `max_execution_time = 60`. The panel shows the effective upload limit under every upload field.
* HTTPS (the session cookie gets `Secure` and HSTS is sent when the request is HTTPS). The login page warns when it is opened over plain HTTP.

## First run

1. Upload the **contents** of `public/` to the web root (see `README.md`).
2. In the Turbify File Manager create an **empty file** `app/storage/setup.allow`.
3. Open `https://<domain>/admin/` → the form to create the first **admin** account appears.
4. After the account is created the panel deletes `setup.allow` (it also deletes it on any later request if it reappears while accounts exist).

Without `setup.allow`, an empty installation only shows instructions — nobody can claim the panel just by finding the URL first.

**Lost the only admin password?** Delete `app/storage/users.php` in the File Manager (this removes all accounts, nothing else), then repeat the first-run steps.

## Permissions (folders PHP must be able to write)

| Folder (relative to the web root) | Why |
|---|---|
| `app/content/` | content JSON (saved by the panel) |
| `app/storage/` | accounts, sessions, audit log, rate-limit counters, backups, build status |
| `uploads/images/`, `uploads/reports/` | uploaded photos / PDFs |
| web root, `activities/`, `projects/`, `stories/`, `assets/css/`, `assets/js/` | pages rebuilt after every save |

Typical shared-hosting values are 755 for folders and 644 for files (PHP runs as the account owner). If PHP runs as another user, use 775/664. The dashboard lists every folder that is not writable, and every save error names the exact folder.

## Security model (does not depend on `.htaccess`)

Turbify may ignore `.htaccess`, so nothing sensitive relies on it:

* **Accounts** — `app/storage/users.php`, a PHP file that `return`s an array (written with `var_export`, atomic rename, `opcache_invalidate`). Requested over HTTP it prints nothing. Passwords: `password_hash` (bcrypt), min length 10; unknown users are verified against a dummy hash (no timing oracle).
* **Other private data** (audit log, rate-limit counters, build status, error log, sessions, content backups) — `.php` files that start with `<?php exit; ?>` followed by JSON/JSON-lines or serialized session data. Temporary files are `.php` too.
* **Sessions** — custom handler storing `app/storage/sessions/<sha256(id)>.php`; session name `bm_admin`; `use_strict_mode`; cookie `HttpOnly`, `SameSite=Strict`, `Secure` on HTTPS, path limited to the admin folder; id regenerated at login; idle timeout 30 min, absolute timeout 8 h; changing/resetting a password or deleting a user ends their other sessions.
* **CSRF** — per-session token on every POST (`hash_equals`), plus an Origin/Referer check: the host must be the request's `Host`/`SERVER_NAME` or the host of `settings.site_url` (for a proxy that rewrites `Host`); `X-Forwarded-Host` is client-controlled and never trusted. Logout is POST-only.
* **Login rate limiting** — 5 failures per IP-hash or per username within 15 min → 15-min lock, one generic message. Only HMACs of IPs/usernames are stored.
* **Roles** — `admin` / `editor`, enforced server-side for every section and action (`adm_can()`).
* **Headers on every admin response** — `Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow` (+ `<meta name="robots" content="noindex,nofollow">`). No inline JS/CSS anywhere (all behaviour in `admin/admin.js`, all styles in `admin/admin.css`).
* **Input** — every value is cleaned (valid UTF-8, no control characters, length limits), enums/slugs are whitelisted, URLs must be `http(s)` (donation links `https://` only — `AdmInput::url(…, ['https_only' => true])` — because `bm_donation_methods()` shows nothing else), emails use `filter_var`, the texts editor rejects unknown keys and preserves `_label` metadata. The texts editor merges `app/lib/texts-defaults.json.php` (shipped by `tools/package.sh` in code updates) into the form and the structure check with `bm_merge_texts()`, like `bm_content('texts')`, so keys added by an update are editable at once; they are written to `texts.json` when their group is saved with an edit. All output is escaped with `e()`.
* **Uploads** — JPEG/PNG/WebP only, ≤10 MB, ≤40 MP, verified with `getimagesize` + `finfo`, then **re-encoded with GD** (EXIF orientation applied, max 1600 px wide + 640 px thumbnail, JPEG q82) which strips EXIF/GPS. Logos keep transparency as PNG. PDFs: `finfo` + `%PDF-` magic, ≤15 MB. SVG is never accepted. Client file names are ignored (random hex names). Deletions are limited to `uploads/images|reports` with a `realpath` prefix check.

## Where data lives

| Path | Content | In git? |
|---|---|---|
| `app/content/*.json` | all editable content | yes |
| `uploads/images/`, `uploads/reports/` | uploads | no (ignored) |
| `app/storage/users.php` | accounts | **never** |
| `app/storage/audit.php` | change log (JSON lines, rotated at ~1.5 MB → last 4000 entries) | no |
| `app/storage/backups/<file>/<YYYYmmdd-HHMMSS>-<rand>.json.php` | automatic backups before each save (last 30 per file). Remove the first line (`<?php exit; ?>`) to get the original JSON. | no |
| `app/storage/build-status.php`, `ratelimit.php`, `secret.php`, `errors.php`, `sessions/` | runtime | no |

## Backups & restore

* Automatic: before every save (see above). Restore from **نسخة احتياطية** in the panel (the restore itself is backed up first and rebuilds the site).
* Full: the ZIP download (content JSON + uploads + README). To restore manually, upload `app/content/*.json` and `uploads/` back via the File Manager, then press **إعادة بناء الموقع** on the dashboard (or run `php app/build.php`).
* If ZipArchive is missing, the panel explains how to download the folders from the File Manager.

## Save flow

`validate → lock → re-read file → backup → bm_json_write() → bm_build()`. If the rebuild fails (any exception or PHP `Error`, not only a build error), the previous file is restored, the site is rebuilt, the editor sees an Arabic message (+ technical details) and keeps the typed values. The last build result is shown on the dashboard. Every successful change is written to the audit log.

## Tests

```bash
tests/admin/run.sh <scratch-dir> [port]   # fresh sandbox copy of public/ → PHP 7.4 lint → unit tests → HTTP tests
                                          #   unit-test.php, unit-content-test.php, http-test.php (stage 1), http-content-test.php (stage 2),
                                          #   then http-host-limits-test.php on php -S restarted with disable_functions=set_time_limit,ini_set,…
php tests/admin/php74-lint.php            # PHP 8-only syntax/functions check for public/admin + public/app/admin
node tests/admin/shots.js <node_modules> http://127.0.0.1:<port> <out-dir> <user> <password> 390,1280   # screenshots
php tests/admin/make-fixtures.php <dir>  # drawn photo + PDF fixtures for the next command
node tests/admin/shots-content.js <node_modules> http://127.0.0.1:<port> <out-dir> <user> <password> <fixtures-dir> 390,1280
                                          # drives the content sections through the real UI (uploads, reorder…) on a SANDBOX
```

`http-test.php` also covers texts keys that exist only in `texts-defaults.json.php` (listed, accepted, saved) and `http://` donation links being refused. `http-content-test.php` creates its own accounts in the sandbox and covers, among others: GPS EXIF removed from uploaded JPEGs, «منجز» refused without the completion confirmation, publishing refused without «تاريخ آخر تحديث» (typed values kept), editor blocked from settings/users, published project/story pages appearing in the build and disappearing after unpublishing, files deleted with their records, stale-form conflicts, and rollback of a failed build (including the photos uploaded with it).

The tests never touch the real `public/` folder (they refuse to run against it). Note that `__DIR__` resolves symlinks, so sandboxes must be **copies**, not symlinks.

## Content sections (stats, projects, stories, reports, galleries)

One file per section in `app/admin/sections/` (`stats.php`, `projects.php`, `stories.php`, `reports.php`, `galleries.php`); shared helpers in `app/admin/collections.php`. Editors and admins may use all five (`adm_role_permissions()`); the router enforces it.

| Section | Routes | File | Rules enforced on the server |
|---|---|---|---|
| أثرنا بالأرقام | `?p=stats` | `stats.json` | value = number ≥ 0 or empty (`null`); review date valid and not in the future; icon from `adm_stat_icons()`; activity from the registry; publishing new/changed visible numbers needs `verified`. Unknown keys of a card are kept. |
| المشروعات | `?p=projects` (`f=ongoing\|done\|draft`), `&id=new\|<id>`, `&id=<id>&stage=new\|<n>` | `projects.json` | title + activity required; slug `[a-z0-9-]`, 2–60, unique; status `ongoing\|done`; **switching to `done` needs `done_confirmed`**; **publishing needs `updated_at`** (§12 «كل مشروع له حالة وتاريخ آخر تحديث»; the example project ships as an undated draft); dates not in the future; `show_financials` needs a cost or donations value; Arabic digits normalised. Stages: date + title required, stored newest first; optional bump of `updated_at`. |
| الحكايات | `?p=stories` (`f=published\|draft`), `&id=new\|<id>` | `stories.json` | type from `bm_story_types()`; title + summary required; slug unique; **publishing, or any change to a published story, needs `privacy_confirmed`**. The type's guide (`texts.json` → `stories.types.<type>.guide`) is shown next to the selector. |
| التقارير | `?p=reports`, `&id=new\|<id>` | `reports.json` | PDF required for a new report (`adm_pdf_upload`); year 19xx/20xx, not in the future; **publishing, or a new file for a published report, needs `review_confirmed`**; id = 10 random hex chars. |
| معرض صور الأنشطة | `?p=galleries`, `&a=<activity>` | `galleries.json` | activity slug whitelisted; the file stays a JSON object (`{}` when empty). |

Common behaviour:

* **Same save flow** as stage 1: `adm_content_save()` (lock → re-read → backup → write → `bm_build()` → restore + rebuild on failure → audit entry). New uploads made during a failed or conflicting save are deleted again.
* **Edit conflicts**: every edit form carries `rev` = `adm_rev(<stored record>)` (16-hex hash). If the record changed meanwhile, nothing is saved, a warning is shown and the typed values stay in the form. Deletes and stage actions check `rev` too.
* **Images** (`adm_images_editor()` / `adm_images_from_post()` / `adm_images_pending()` / `adm_images_process()`): current images are posted as `imgs[n][k|alt|caption|del]` where `k = substr(sha1(src), 0, 12)`; only keys of the record being edited are accepted, so a form can never reference or delete another record's file. The posted row order is the saved order (admin.js moves rows client-side; without JS, «تقديم/تأخير» are submit buttons `imgs_move=<key>:up|down`). New files come from `imgs_new[]` with a required **Arabic** description per file (`imgs_alt_each[i]`, created by admin.js) or the shared `imgs_alt`. Descriptions are validated before any file is processed; files that are not images then fail individually (the others are saved and a warning lists the rejected ones). Images go through stage 1's `adm_image_upload()` (re-encoded, EXIF/GPS stripped). An invisible first submit button (`adm_enter_submit()`) keeps Enter from triggering a reorder button.
* **Deleting files**: after a successful save, removed images/PDFs are deleted with `adm_delete_unreferenced()`, which first collects every `uploads/…` path still referenced by any content JSON (`adm_uploads_referenced()`) and only deletes files nobody uses (and only inside `uploads/images|reports`, via `adm_delete_upload()`). Consequence: **restoring an older JSON backup does not bring deleted photos back** — use the ZIP backup for that.
* **Pages**: published projects/stories get `projects/<id>.html` / `stories/<id>.html`; unpublishing, renaming or deleting removes the old page on the next build (the build deletes every page in `projects/` and `stories/` it did not render).
* **Dates** use the site timezone (Africa/Cairo, `app/bootstrap.php`) for "today".
* **Audit actions**: `stats.save`, `projects.create|save|delete|stage|stage_delete`, `stories.create|save|delete`, `reports.create|save|delete`, `galleries.upload|save|delete`; details name the record and what changed (e.g. «الحالة ← منجز (أُكّد إثبات الإنجاز الفعلي)»).

## Adding another section

Add it to `adm_sections()` (`sections.php`), create `app/admin/sections/<key>.php`, list the key for editors in `adm_role_permissions()` if they need it, and add Arabic audit labels in `adm_audit_labels()`. Until the file exists the menu shows «قريبًا» and `sections/_stub.php` is rendered.

Section file contract (included once per request, inside the layout):

```php
<?php
defined('BM_APP') || exit;               // $user and $section are available
AdmPage::$title = 'المشروعات';
AdmPage::$lead  = '…';

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {   // CSRF already checked by the router
    $in = new AdmInput($_POST);
    $title = $in->text('title', 'عنوان المشروع', ['max' => 140, 'required' => true]);
    $status = $in->enum('status', 'الحالة', bm_project_statuses(), 'ongoing');
    $activity = $in->slug('activity', 'النشاط', array_keys(bm_activity_registry()));
    $images = [];
    foreach (adm_files('images') as $i => $file) {               // <input type=file name="images[]" multiple>
        try {
            $images[] = adm_image_upload($file, ['alt' => (string) adm_post('alt.' . $i, $in->raw('alt'))]);
        } catch (AdmUserError $e) {
            $in->error('images', $e->getMessage());
        }
    }
    if ($in->ok()) {
        try {
            $r = adm_content_save('projects', function (array $list) use ($title, $status, $activity, $images) {
                // …modify $list, keep the schema of bm_project_defaults()…
                return $list;
            }, 'projects.save', '«' . $title . '»');
            adm_flash('success', 'حُفظ المشروع. ' . adm_build_summary($r['report']));
            adm_redirect('projects');
        } catch (AdmUserError $e) {
            foreach ($images as $img) { adm_delete_image($img); }
            adm_flash('error', $e->getMessage(), $e->details);
        }
    } else {
        foreach ($images as $img) { adm_delete_image($img); }
    }
    AdmForm::$errors = $in->errors();
}
// render: adm_form_open(['files' => true, 'dirty' => true]), adm_field([...]), adm_image_field([...]),
// adm_pdf_field([...]), adm_repeater([...]), adm_submit(...), adm_action_button([... 'confirm' => '…'])
```

Library APIs are documented at the top of each file in `app/admin/`:

* `forms.php` — `adm_field`, `adm_form_open/close`, `adm_submit`, `adm_action_button`, `adm_link_button`, `adm_repeater`, `adm_image_field`, `adm_pdf_field`, `adm_errors_summary`, `AdmForm::$errors`.
* `input.php` — `AdmInput` (text/url/email/phone/year/date/number/enum/slug/bool/lines) and the `adm_clean*` functions.
* `uploads.php` — `adm_files`, `adm_image_upload`, `adm_image_process`, `adm_pdf_upload`, `adm_delete_upload`, `adm_delete_image`, `adm_privacy_reminder`.
* `content.php` — `adm_content_load`, `adm_content_save`, `adm_rebuild`, `adm_build_summary`, backups.
* `collections.php` — records (`adm_list_load/index/ids`, `adm_rev`, `adm_req_id`), slugs (`adm_slug_from_post`, `adm_unique_slug`), options (`adm_activity_options`, `adm_story_type_options`, `adm_stat_icons`), the image-list editor (`adm_images_editor`, `adm_images_from_post`, `adm_images_pending`, `adm_images_process`, `adm_enter_submit`) and `adm_delete_unreferenced`.
* `ui.php` — `AdmPage`, `adm_url`, `adm_redirect`, `adm_flash`, `adm_card`, `adm_badge`, `adm_alert`, `adm_empty`, dates.
* `audit.php` — `adm_audit()` and the Arabic action labels; `users.php` — `adm_can()` and the role map; `sections.php` — navigation registry.

Keep the code PHP 7.4-compatible (run `php tests/admin/php74-lint.php`), never add inline `<script>`/`style=""` (CSP), and keep the JSON schemas defined in `app/lib/data.php`.
