export default function HeaderLogo() {

    return (
        <>
            {/* HeaderLogo */}
            <div className="et_pb_column et_pb_column_2_5 et_pb_column_0_tb_header  et_pb_css_mix_blend_mode_passthrough">
                <div className="et_pb_module et_pb_image et_pb_image_0_tb_header">
                    <a href="{process.env.PUBLIC_URL}/" >
                        <span className="et_pb_image_wrap " >
                            <img
                                decoding="async"
                                src="{process.env.PUBLIC_URL}/assets/img/logo.jpg"
                                alt="Law Offices of Villard Bastien"
                                title=""
                                width={500}
                                height={111}
                            />
                        </span>
                    </a>
                </div>
            </div>
        </>
    );

}
