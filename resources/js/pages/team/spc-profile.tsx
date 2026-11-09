import PublicPage from '@/layouts/public-page';

/**
 * Dedicated State Project Coordinator profile — a special editorial page.
 * The copy below is supplied editorial content and is intentionally NOT
 * managed through the ordinary Team Member form or any admin CRUD.
 */
const PROFILE_TITLE = 'Gombe L-PRES Coordinator, Professor U.B. Abubakar: A Distinguished Leader in Veterinary Medicine';

const PORTRAIT = '/images/lpres/spc-prof-u-b-abubakar.png';

const SECTIONS: { heading: string; paragraphs: string[] }[] = [
    {
        heading: 'Personal Information',
        paragraphs: [
            'Professor Usman Bello Abubakar was born on January 22nd, 1970, in Tongo, Funakaye Local Government Area of Gombe State. He is a highly respected figure in the field of veterinary medicine. Professor U. B. Abubakar’s journey to excellence has been marked by a strong work ethic and a dedication to continuous learning. He possesses diverse experience in academia, public service, and community leadership, coupled with extensive knowledge, skills, and expertise.',
        ],
    },
    {
        heading: 'Educational Qualifications',
        paragraphs: [
            'Professor Abubakar possesses a strong academic foundation with a distinguished record of achievements. He earned his Doctor of Veterinary Medicine (DVM) from the University of Maiduguri, Borno State, in 2005 and furthered his studies at Ahmadu Bello University, Zaria, where he obtained a Master of Science (MSc) Degree in Large Animal Medicine in 2010. Abubakar continued his academic pursuits at the same institution, culminating in the award of a Doctor of Philosophy (PhD) in Large Animal Medicine in 2016.',
            'Recognizing his exceptional contributions to the field, he was awarded the prestigious Fellowship of the College of Veterinary Surgeons of Nigeria (FCVSN) in Large Animal Medicine by the Veterinary Council of Nigeria, Abuja, in 2021.',
        ],
    },
    {
        heading: 'Professional/Working Experience',
        paragraphs: [
            'Professor Abubakar boasts a distinguished career spanning over three decades, encompassing both public service and academia. He began his professional journey in 1991 as a Livestock Assistant at the Bauchi State Ministry of Agriculture, Veterinary and Livestock Services. He subsequently joined the Gombe State Ministry of Agriculture, Veterinary and Livestock Services, where he progressed through the ranks, serving as Assistant Chief Livestock Overseer (1994-1997), Chief Livestock Overseer (1997-2003), and Livestock Superintendent (2003-2006) before transitioning to the role of Veterinary Officer until 2007.',
            'In 2007, Professor Abubakar embarked on an academic career at Ahmadu Bello University, Zaria, joining the Department of Veterinary Medicine as an Assistant Lecturer. He steadily progressed through the academic ranks, attaining the positions of Lecturer II (2010-2013), Lecturer I (2013-2016), Senior Lecturer (2016-2019), and ultimately achieving the prestigious position of Professor in 2022.',
            'Beyond his academic pursuits, Professor Abubakar has actively engaged in various research and development projects. He served as a Technical Team Member for the Centre of Excellence on Tuberculosis, Rabies and Brucellosis at ABU, a project funded by the World Bank through the Science and Technology Education Post Basic (STEP-B) program.',
            'He has also led several significant initiatives, including the Water Canalization project into the Wawa Zange Grazing Reserve, a crucial component of the Feed the Future Nigerian Rural Resilience Activity (RRA) funded by USAID and implemented by a consortium led by Mercy Corps. He also led the development of Livestock Investible Business Cases in the Wawa Zange Grazing Reserve under the same USAID-funded RRA program.',
            'Currently, Professor Abubakar serves as the esteemed State Coordinator for the Gombe State Livestock Productivity and Resilience Support (L-PRES) Project, playing a pivotal role in driving the transformation of the livestock sector in Gombe State.',
            'Professor Abubakar has a dedicated passion for teaching veterinary medicine. Throughout his tenure at Ahmadu Bello University, Zaria, he has instructed a wide range of undergraduate and postgraduate courses.',
            'His undergraduate teaching responsibilities have encompassed subjects such as Large Animal Practice I & II, Ambulatory Practice I & II, Clinical Conference I & II, Bacterial Diseases, Viral Diseases, Clinical Techniques, Principles of Medicine (Cattle), Introduction to Animal Behavior, and Animal Handling (Cattle).',
            'At the postgraduate level, he has taught courses including Topics in Infectious Diseases I & II, Advanced Clinics I & II, Advanced Monogastric Internal Medicine, Advanced Herd Health Management, and Advanced Clinical Gastroenterology. Professor Abubakar is a dedicated mentor to his students, providing guidance and support throughout their academic journey.',
        ],
    },
    {
        heading: 'Professional Development',
        paragraphs: [
            'Professor Abubakar is committed to continuous professional development. He has actively engaged in numerous professional development activities throughout his career. He has presented his research at esteemed international conferences, such as the 18th Union Conference of the African Region for the International Union Against Tuberculosis and Lung Disease in Abuja (March 3-5, 2011) and the 16th International Congress on Infectious Diseases in Cape Town, South Africa (April 2-5, 2014).',
            'Professor Abubakar actively engages with professional societies, regularly attending their annual conferences. He has participated in the 51st Annual Conference of the Agricultural Society of Nigeria (Abuja, October 23-27, 2017), the 6th International Conference on Rabies in West Africa (Abuja, August 19-24, 2019), and the 46th and 47th Annual Conferences of the Nigerian Society for Animal Production, hosted by Federal University Dutsin-Ma (March 14-18, 2021) and the University of Jos (March 13-17, 2022), respectively.',
            'He has also participated in a diverse range of training programs, including the Training on Climate Smart Animal Husbandry Practices for increased productivity at the National Animal Production Research Institute, ABU, Zaria (2013), the Training Workshop on Fish, Poultry and Livestock feed formulation using local raw materials in Nigeria at the College of Agriculture and Animal Science Mando, Kaduna State (2014), workshops on Fish Management, Fish Feeding and Feed Formulation using locally available materials at the Department of Animal Science, ABU, Zaria (2016), and the Training Workshop on Reducing Livestock Production Losses in Nigeria at the National Animal Production Research Institute, ABU, Zaria (2017).',
            'Others are training workshops on Poultry Production, Housing, Feeding, and Vaccination at the National Animal Production Research Institute, ABU, Zaria (2018), the Training Workshop on Livestock Value Chain organized by Alar Denmack, Institute of Agricultural Research (2021), the Virtual Training on Livestock Transformation Plan organized by NLTP Abuja (2022), a Three-day Workshop on Climate Change Awareness and Adaptation, alternative energy and energy efficiency conducted by the North East Development Commission (2022), and the Training Workshop on Scaling up Sustainable Livestock Businesses and Coordination at the Business School, Ahmadu Bello University, Zaria (2023).',
            'Prof U. B. has also participated in the Veterinary Council of Nigeria’s Professional Continuing Education Seminars held in Arewa House, Kaduna (October 5-9, 2007), Zaranda, Bauchi (August 17, 2009), and Abuja (October 23, 2012). He has also attended the Workshop on Kaduna State Livestock Sector Policy Study (March 27, 2008) and the technical Session/Workshop for Innovators of Tomorrow Special Grand, organized by the World Bank in collaboration with the National System of Innovation, in Abuja (September 14, 2010).',
        ],
    },
    {
        heading: 'Research Focus/Publications/Articles',
        paragraphs: [
            'Professor Abubakar has a distinguished publication record, with over 60 articles published in peer-reviewed journals and books. His research interests encompass a broad spectrum, spanning poultry production, animal diseases, and public health.',
            'Early in his career, Professor Abubakar focused on poultry production, investigating factors limiting commercial poultry production in Gombe Metropolis. Subsequently, his research shifted towards the critical area of tuberculosis in livestock. He has conducted extensive research on the prevalence of bovine tuberculosis in various species, including cattle, camels, and goats, across different regions of Nigeria. His research methodologies are diverse, encompassing post-mortem examinations, serological tests, and molecular techniques. Notably, his research has significantly advanced our understanding of tuberculosis transmission, including zoonotic transmission between humans and cattle.',
            'His research findings have been disseminated through a wide range of prestigious international and national journals, including the Nigerian Veterinary Journal, Veterinary Research, the Journal of Camels Research and Practice, the British Journal of Medicine and Medical Research, the International Journal of Tropical Medicine, and many others. These publications have significantly contributed to the advancement of veterinary knowledge and informed public health interventions for the control of tuberculosis in Nigeria.',
        ],
    },
    {
        heading: 'Awards and Recognition',
        paragraphs: [
            'Professor Abubakar’s distinguished career has been recognized through several prestigious awards and grants. He received the 2010 Innovation of Tomorrow Research Grants from the Science and Technology Education Post Basic (STEP-B) program, funded by the World Bank and administered by the Federal Ministry of Education. He also played a key role in establishing the Centre of Excellence on Tuberculosis, Rabies and Brucellosis at ABU, another initiative supported by the STEP-B program.',
            'Furthermore, his contributions have been acknowledged by his peers. He was honored with the Outstanding ARD Award by the Association of Resident Doctors of Ahmadu Bello University Teaching Hospital, Shika-Zaria, Kaduna State, on December 12, 2015, in recognition of his notable contribution toward the ideals of the association.',
            'His research endeavors have also received significant recognition. He was awarded the Agricultural Research Council Research Competitive Grant for his project on “Epidemiology of Mycobacterium bovis Infection in Animals and Humans in North East part of Northern Nigeria” (2010-2012). He also received the Tertiary Education Trust Fund National Research Competitive Grant Award for his project on “Epidemiology of Mycobacterium bovis Infection in Cattle and Humans in North West part of Northern Nigeria” (2013-Date).',
            'Finally, in recognition of his exceptional service as National Vice-President of the Nigerian Infectious Diseases Society from 2015 to 2019, he was awarded the Outstanding Service Award by the society.',
        ],
    },
    {
        heading: 'Leadership and Community Service',
        paragraphs: [
            'Professor Abubakar is a strong advocate for public service and actively engages in initiatives that promote animal health awareness and empower local communities. He serves as a member of the Poultry Production Awareness Committee at the national and state levels. He also leads the Gombe State Livestock Transformation Management Project Committee. Within his faculty, he actively engages in educating farmers on Bovine Tuberculosis Prevention and Control.',
            'Beyond his professional endeavors, Professor Abubakar demonstrates a strong commitment to his university community. He has served on numerous committees, including the Ahmadu Bello University Committee on HIV/AIDS Awareness and the University Senate. He has also held leadership positions within the Academic Staff Union of Universities (ASUU), demonstrating his dedication to advocating for the rights and welfare of his colleagues.',
            'Professor Abubakar’s exceptional contributions in teaching, research, community service, and leadership solidify his position as a distinguished leader in the field of veterinary medicine. He is a valuable asset to his community, his profession, and the nation.',
        ],
    },
    {
        heading: 'Career Goals and Aspirations',
        paragraphs: [
            'Professor Abubakar is a highly motivated and results-oriented professional with a strong work ethic. He strives for excellence in all his endeavors, consistently meeting deadlines and exceeding expectations. He thrives in dynamic environments, effectively managing work under pressure while readily adapting to new challenges. As a strong team player, he leverages his analytical and problem-solving skills to contribute effectively in collaborative settings. He is proactive and self-motivated, consistently striving to achieve his professional goals.',
        ],
    },
];

export default function SpcProfile() {
    return (
        <PublicPage
            title={PROFILE_TITLE}
            description="The distinguished career of Professor U.B. Abubakar, State Coordinator of the Gombe State L-PRES Project."
            eyebrow="State Project Coordinator"
            breadcrumbs={[{ title: 'Project Team', href: '/project-team' }, { title: 'SPC Profile', href: '' }]}
        >
            {/* Profile header: portrait, name and the opening designation panel */}
            <div className="mb-14 flex flex-col gap-8 border border-[#e1e8ea] bg-[#f1f5f6] p-6 md:flex-row md:items-center lg:p-10">
                <img
                    src={PORTRAIT}
                    alt="Portrait of Professor U.B. Abubakar"
                    width={280}
                    height={340}
                    className="block w-[220px] shrink-0 self-center bg-lpres-mist object-cover lg:w-[280px]"
                    decoding="async"
                />
                <div>
                    <p className="text-lpres-green text-sm font-bold tracking-[1px] uppercase">Meet the</p>
                    <h2 className="text-lpres-deep mt-1 text-2xl leading-9 font-extrabold lg:text-3xl lg:leading-10">
                        Professor Usman Bello Abubakar
                    </h2>
                    <p className="mt-2 text-base text-[#4b5e65]">State Coordinator — Gombe State Livestock Productivity and Resilience Support (L-PRES) Project</p>
                </div>
            </div>

            <div className="space-y-12">
                {SECTIONS.map((section) => (
                    <section key={section.heading} aria-labelledby={section.heading.toLowerCase().replace(/[\s/]+/g, '-')}>
                        <h2
                            id={section.heading.toLowerCase().replace(/[\s/]+/g, '-')}
                            className="text-lpres-deep relative mb-4 inline-block text-2xl leading-8 font-extrabold"
                        >
                            <span aria-hidden="true" className="bg-lpres-green absolute -top-[13px] left-0 block h-0.5 w-[35px]" />
                            {section.heading}
                        </h2>
                        {section.paragraphs.map((paragraph, index) => (
                            <p key={index} className="mb-4 text-base leading-[27px] text-[#4b5e65] last:mb-0">
                                {paragraph}
                            </p>
                        ))}
                    </section>
                ))}
            </div>

            <div className="mt-12">
                <a
                    href="/project-team"
                    className="focus-visible:outline-lpres-deep inline-flex h-[46px] items-center bg-lpres-deep px-7 text-sm font-extrabold tracking-[0.5px] text-white uppercase transition-colors hover:bg-lpres-green focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-none"
                >
                    Back to the Project Team
                </a>
            </div>
        </PublicPage>
    );
}
